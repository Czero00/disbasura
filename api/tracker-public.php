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
require_once __DIR__ . '/../includes/gps_helpers.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['resident', 'leader'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sign in as a resident or sitio leader to view truck locations.']);
    exit;
}

$db = get_db();
echo json_encode(get_visible_truck_locations($db, (string)($_SESSION['sitio'] ?? '')));
