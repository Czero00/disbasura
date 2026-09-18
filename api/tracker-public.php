<?php
/**
 * Live GPS Tracking (view) — Resident & Sitio Leader.
 * Gap fix: Table 18 (Feature x Role matrix) marks this feature ✅
 * for Resident and Sitio Leader, but only the admin had a tracker
 * page/endpoint. This mirrors api/tracker.php's data exactly, just
 * opened to the resident/leader session instead of the admin one.
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['resident', 'leader'])) {
    echo json_encode([]); exit;
}

$db = get_db();
$trucks = $db->query(
    "SELECT id, full_name, sitio, status, truck_lat, truck_lng, truck_updated_at
     FROM collectors WHERE truck_lat IS NOT NULL"
)->fetchAll();
echo json_encode($trucks);
