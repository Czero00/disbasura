<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (!isset($_SESSION['collector_id'])) { http_response_code(403); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$lat  = $data['lat'] ?? null;
$lng  = $data['lng'] ?? null;
if ($lat && $lng) {
    $db = get_db();
    $db->prepare("UPDATE collectors SET truck_lat=?,truck_lng=?,truck_updated_at=? WHERE id=?")
       ->execute([$lat, $lng, now_pht(), $_SESSION['collector_id']]);
}
http_response_code(204);
