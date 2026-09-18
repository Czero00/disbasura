<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if ($_SESSION['role'] !== 'leader') { header('Location: /disbasura/resident/tracker.php'); exit; }

$sitio = $_SESSION['sitio'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Live Tracker — DisBasura</title>
<link rel="stylesheet" href="/disbasura/assets/css/base.css"/>
<link rel="stylesheet" href="/disbasura/assets/css/dashboard.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
  #map{width:100%;height:520px;border-radius:16px;border:1px solid var(--border-light,#e4ede8);overflow:hidden;margin:1.25rem 0}
  .truck-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}
  .truck-card{background:#fff;border-radius:12px;border:1px solid var(--border-light,#e4ede8);padding:1.1rem;display:flex;align-items:center;gap:.85rem}
  .truck-icon{width:44px;height:44px;border-radius:50%;background:#e8f5ee;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
</style>
</head>
<body style="background:var(--bg-page,#f4f9f6);padding:1.5rem">
<div style="max-width:1000px;margin:0 auto">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:.5rem">
    <div style="display:flex;align-items:center;gap:.75rem">
      <a href="/disbasura/leader/dashboard.php" style="color:var(--text-mid);text-decoration:none">←</a>
      <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.3rem;font-weight:800;margin:0">📍 Live Tracker</h1>
    </div>
    <span style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--text-mid)">
      <span style="width:9px;height:9px;border-radius:50%;background:#2d8653;display:inline-block;animation:pulse 1.5s infinite"></span>Auto-refreshes every 15s
    </span>
  </div>
  <p style="font-size:.85rem;color:var(--text-mid);margin:0 0 1rem">Live collector locations — your sitio is <?= htmlspecialchars($sitio) ?></p>

  <div id="map"></div>
  <div class="truck-list" id="truckList">
    <div style="grid-column:1/-1;padding:2rem;text-align:center;color:var(--text-mid)">Loading truck locations…</div>
  </div>
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
        list.innerHTML = '<div style="grid-column:1/-1;padding:2rem;text-align:center;color:var(--text-mid)">No collectors are currently sharing their location.</div>';
        return;
      }
      list.innerHTML = trucks.map(t => `
        <div class="truck-card">
          <div class="truck-icon">🚛</div>
          <div>
            <strong style="display:block;font-size:.88rem;font-weight:700">${t.full_name}</strong>
            <span style="display:block;font-size:.75rem;color:var(--text-mid)">📍 ${t.sitio}</span>
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
