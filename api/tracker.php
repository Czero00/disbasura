<?php
// This endpoint is shared by the admin tracker and lives outside /admin.
$_GET['role'] = 'admin';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gps_helpers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode([]); exit;
}
$db = get_db();
echo json_encode(get_visible_truck_locations($db));
