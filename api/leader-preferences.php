<?php
require_once __DIR__ . '/../middleware/resident_auth.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['role'] ?? '') !== 'leader') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sitio leader access is required.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST is required.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];
$uid = (int)$_SESSION['user_id'];
$prefs = get_preferences($uid);
$dark = (int)($prefs['dark_mode'] ?? 0);
$language = ($prefs['language'] ?? 'en') === 'fil' ? 'fil' : 'en';

if (array_key_exists('toggle_dark', $data)) {
    $dark = $dark ? 0 : 1;
} elseif (array_key_exists('language', $data)) {
    if (!in_array($data['language'], ['en', 'fil'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unsupported language.']);
        exit;
    }
    $language = $data['language'];
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No preference was provided.']);
    exit;
}

try {
    save_preferences($uid, $dark, $language);
    echo json_encode(['ok' => true, 'dark_mode' => $dark, 'language' => $language]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save the preference.']);
}
