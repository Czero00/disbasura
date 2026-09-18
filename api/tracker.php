<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode([]); exit;
}
$db = get_db();
$trucks = $db->query("SELECT id,full_name,sitio,status,truck_lat,truck_lng,truck_updated_at FROM collectors WHERE truck_lat IS NOT NULL")->fetchAll();
echo json_encode($trucks);
