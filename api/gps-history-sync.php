<?php
$_GET['role'] = 'collector';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gps_helpers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['collector_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Collector sign-in required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$points = is_array($payload['points'] ?? null) ? array_slice($payload['points'], 0, 100) : [];
if (!$points) {
    http_response_code(400);
    echo json_encode(['error' => 'No saved GPS points were provided.']);
    exit;
}

$db = get_db();
try {
    ensure_tracking_history_schema($db);
    $insert = $db->prepare('INSERT IGNORE INTO tracking_history (collector_id,latitude,longitude,accuracy,client_point_id,`timestamp`) VALUES (?,?,?,?,?,?)');
    $saved = 0;
    $skipped = 0;
    $collectorId = (int)$_SESSION['collector_id'];
    foreach ($points as $point) {
        $lat = filter_var($point['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($point['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $accuracy = filter_var($point['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
        $capturedAt = normalize_gps_capture_time((string)($point['captured_at'] ?? ''));
        $pointId = (string)($point['id'] ?? '');
        if ($lat === false || $lng === false || $accuracy === false || $accuracy > 100
            || $lat < 10.15 || $lat > 10.52 || $lng < 123.70 || $lng > 124.12
            || !$capturedAt || !preg_match('/^[a-zA-Z0-9-]{16,36}$/', $pointId)) {
            $skipped++;
            continue;
        }
        $insert->execute([$collectorId, $lat, $lng, $accuracy, $pointId, $capturedAt]);
        $saved += $insert->rowCount();
    }
    echo json_encode(['accepted' => $saved, 'skipped' => $skipped]);
} catch (Throwable $e) {
    error_log('DisBasura offline GPS history sync failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Saved GPS history could not be synchronized.']);
}
