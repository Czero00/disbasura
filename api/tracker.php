<?php
// This endpoint is shared by the admin tracker and lives outside /admin.
$_GET['role'] = 'admin';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode([]); exit;
}
$db = get_db();
$trucks = $db->query("SELECT id,full_name,sitio,status,truck_lat,truck_lng,truck_updated_at FROM collectors WHERE truck_lat BETWEEN 10.15 AND 10.52 AND truck_lng BETWEEN 123.70 AND 124.12")->fetchAll();
echo json_encode($trucks);
