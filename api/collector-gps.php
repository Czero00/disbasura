<?php
// This endpoint sits under /api, so session.php cannot infer the collector
// session from the URL path by itself.
$_GET['role'] = 'collector';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/gps_helpers.php';
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

$updatedAt = now_pht();
$collectorId = (int)$_SESSION['collector_id'];
$db->prepare('UPDATE collectors SET truck_lat=?,truck_lng=?,truck_updated_at=? WHERE id=?')
   ->execute([$lat, $lng, $updatedAt, $collectorId]);

// Keep a route breadcrumb every 30 seconds. A history write failure must not
// prevent the current live position from being shared.
try {
    ensure_tracking_history_schema($db);
    $lastPoint = $db->prepare('SELECT MAX(`timestamp`) FROM tracking_history WHERE collector_id=?');
    $lastPoint->execute([$collectorId]);
    $lastAt = $lastPoint->fetchColumn();
    if (!$lastAt) {
        $shouldRecord = true;
    } else {
        $zone = new DateTimeZone('Asia/Manila');
        $lastTime = new DateTimeImmutable((string)$lastAt, $zone);
        $currentTime = new DateTimeImmutable($updatedAt, $zone);
        $shouldRecord = ($currentTime->getTimestamp() - $lastTime->getTimestamp()) >= GPS_HISTORY_SAMPLE_SECONDS;
    }
    if ($shouldRecord) {
        $pointId = (string)($data['point_id'] ?? bin2hex(random_bytes(16)));
        if (!preg_match('/^[a-zA-Z0-9-]{16,36}$/', $pointId)) $pointId = bin2hex(random_bytes(16));
        $db->prepare('INSERT IGNORE INTO tracking_history (collector_id,latitude,longitude,accuracy,client_point_id,`timestamp`) VALUES (?,?,?,?,?,?)')
           ->execute([$collectorId, $lat, $lng, $accuracy, $pointId, $updatedAt]);
    }
} catch (Throwable $e) {
    error_log('DisBasura GPS history write failed: ' . $e->getMessage());
}
http_response_code(204);
