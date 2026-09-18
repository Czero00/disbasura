<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

$uid = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if (!$uid) { echo json_encode(['ok'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true) ?? [];
if (isset($data['toggle_dark'])) {
    $prefs = get_preferences($uid);
    $new_dark = $prefs['dark_mode'] ? 0 : 1;
    save_preferences($uid, $new_dark, $prefs['language'] ?? 'en');
}
if (isset($data['language'])) {
    $prefs = get_preferences($uid);
    save_preferences($uid, $prefs['dark_mode'] ?? 0, $data['language']);
}
echo json_encode(['ok'=>true]);
