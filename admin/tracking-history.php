<?php
require_once __DIR__ . '/../middleware/admin_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/gps_helpers.php';
$db = get_db();
ensure_tracking_history_schema($db);
$collectors = $db->query('SELECT id,full_name,sitio FROM collectors ORDER BY full_name')->fetchAll();
$collectorId = max(0, (int)($_GET['collector_id'] ?? 0));
$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-7 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !DateTime::createFromFormat('!Y-m-d', $from)) $from = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !DateTime::createFromFormat('!Y-m-d', $to)) $to = date('Y-m-d');
$sql = 'SELECT h.collector_id,c.full_name,c.sitio,h.latitude,h.longitude,h.accuracy,h.`timestamp`
        FROM tracking_history h JOIN collectors c ON c.id=h.collector_id
        WHERE h.`timestamp`>=? AND h.`timestamp`<DATE_ADD(?, INTERVAL 1 DAY)';
$params = [$from . ' 00:00:00', $to . ' 00:00:00'];
if ($collectorId) { $sql .= ' AND h.collector_id=?'; $params[] = $collectorId; }
$sql .= ' ORDER BY h.`timestamp` DESC LIMIT 2000';
$stmt = $db->prepare($sql); $stmt->execute($params); $points = $stmt->fetchAll();
$route = array_reverse($points);
$unread = get_unread_admin_count((int)$_SESSION['admin_id']);
require_once __DIR__ . '/../includes/base_admin.php';
render_admin_header('gps_history', $unread, 'GPS Route History — DisBasura');
?>
<div class="page-header-row"><div class="page-header"><h1>GPS Route History</h1><p>Recorded collector positions. Locations are sampled about every 30 seconds while tracking is online.</p></div></div>
<section class="panel" style="padding:1rem;margin-bottom:1rem">
  <form method="GET" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap">
    <label>Collector<br><select name="collector_id" style="padding:.55rem;border:1px solid var(--border);border-radius:8px"><option value="0">All collectors</option><?php foreach($collectors as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $collectorId===(int)$c['id']?'selected':'' ?>><?= e($c['full_name'].' — '.$c['sitio']) ?></option><?php endforeach; ?></select></label>
    <label>From<br><input type="date" name="from" value="<?= e($from) ?>" required></label>
    <label>To<br><input type="date" name="to" value="<?= e($to) ?>" required></label>
    <button class="btn-approve" type="submit">Show route</button>
  </form>
</section>
<div id="routeMap" style="height:420px;border-radius:14px;border:1px solid var(--border-light);margin-bottom:1rem"></div>
<section class="panel" style="padding:1rem;overflow:auto">
  <p><strong><?= count($points) ?></strong> points found (maximum 2,000 shown). Offline-collected points are synchronized with their capture times.</p>
  <table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:.55rem">Collector</th><th style="text-align:left;padding:.55rem">Time (Asia/Manila)</th><th style="text-align:left;padding:.55rem">Latitude</th><th style="text-align:left;padding:.55rem">Longitude</th><th style="text-align:left;padding:.55rem">Accuracy</th></tr></thead><tbody>
  <?php foreach($points as $point): ?><tr><td style="padding:.55rem;border-top:1px solid var(--border-light)"><?= e($point['full_name']) ?></td><td style="padding:.55rem;border-top:1px solid var(--border-light)"><?= e($point['timestamp']) ?></td><td style="padding:.55rem;border-top:1px solid var(--border-light)"><?= e((string)$point['latitude']) ?></td><td style="padding:.55rem;border-top:1px solid var(--border-light)"><?= e((string)$point['longitude']) ?></td><td style="padding:.55rem;border-top:1px solid var(--border-light)"><?= $point['accuracy']===null?'—':e((string)round((float)$point['accuracy'])).' m' ?></td></tr><?php endforeach; ?>
  <?php if(!$points): ?><tr><td colspan="5" style="padding:1rem;text-align:center">No route points found for this range.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const routePoints=<?= json_encode(array_map(static fn($p)=>[(float)$p['latitude'],(float)$p['longitude']],$route), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
const routeMap=L.map('routeMap').setView([10.3157,123.8854],12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap contributors'}).addTo(routeMap);
if(routePoints.length){L.polyline(routePoints,{color:'#237247',weight:4}).addTo(routeMap);routePoints.forEach((p,i)=>{if(i===0||i===routePoints.length-1)L.circleMarker(p,{radius:7,color:'#237247'}).addTo(routeMap).bindPopup(i===0?'Route start':'Latest point');});routeMap.fitBounds(L.latLngBounds(routePoints),{padding:[24,24]});}
</script>
<?php render_admin_footer(); ?>
