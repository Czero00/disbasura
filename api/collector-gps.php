<?php
// This endpoint sits under /api, so session.php cannot infer the collector
// session from the URL path by itself.
$_GET['role'] = 'collector';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (!isset($_SESSION['collector_id'])) { http_response_code(403); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$db = get_db();
if (($data['active'] ?? true) === false) {
    $db->prepare('UPDATE collectors SET truck_lat=NULL,truck_lng=NULL,truck_updated_at=NULL WHERE id=?')
       ->execute([$_SESSION['collector_id']]);
    http_response_code(204);
    exit;
}

$lat = filter_var($data['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($data['lng'] ?? null, FILTER_VALIDATE_FLOAT);
$accuracy = filter_var($data['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || $accuracy === false || $accuracy > 100
    || $lat < 10.15 || $lat > 10.52 || $lng < 123.70 || $lng > 124.12) {
    http_response_code(422);
    echo json_encode(['error' => 'GPS fix is outside the Cebu City service area or not accurate enough.']);
    exit;
}

$db->prepare('UPDATE collectors SET truck_lat=?,truck_lng=?,truck_updated_at=? WHERE id=?')
   ->execute([$lat, $lng, now_pht(), $_SESSION['collector_id']]);
http_response_code(204);
