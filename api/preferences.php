<?php
// This endpoint is currently used by the admin panel. Its URL is under /api,
// so the normal path-based session selector would otherwise open the resident
// cookie instead of the administrator's separate session.
if (session_status() === PHP_SESSION_NONE) {
    session_name('disbasura_admin');
    session_start();
}
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

if (($_SESSION['admin_role'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'Please sign in as an administrator.']);
    exit;
}

// Negative IDs reserve a separate key range for administrator preferences;
// resident and leader IDs use positive keys in the same table.
$uid = -abs((int)$_SESSION['admin_id']);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
try {
    if (isset($data['toggle_dark'])) {
        $prefs = get_preferences($uid);
        $new_dark = !empty($prefs['dark_mode']) ? 0 : 1;
        save_preferences($uid, $new_dark, $prefs['language'] ?? 'en');
        echo json_encode(['ok'=>true, 'dark_mode'=>$new_dark]);
        exit;
    }
    if (isset($data['language'])) {
        $prefs = get_preferences($uid);
        save_preferences($uid, (int)($prefs['dark_mode'] ?? 0), $data['language']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Could not save the theme preference.']);
    exit;
}
echo json_encode(['ok'=>true]);
