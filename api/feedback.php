<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$sid  = (int)($data['schedule_id'] ?? 0);
$rating = max(1, min(5, (int)($data['rating'] ?? 5)));
$comment = trim($data['comment'] ?? '');
$uid = $_SESSION['user_id'];

if (!$sid) { echo json_encode(['ok'=>false,'error'=>'Missing schedule_id']); exit; }

$db = get_db();
$feedbackColumns = array_column($db->query('SHOW COLUMNS FROM feedback')->fetchAll(), 'Field');
// Check schedule exists and is completed and in user's sitio
$sched = $db->query("SELECT * FROM schedules WHERE id=$sid AND status='completed'")->fetch();
if (!$sched) { echo json_encode(['ok'=>false,'error'=>'Schedule not found']); exit; }

try {
    $userColumn = null;
    foreach (['user_id', 'rated_by', 'resident_id'] as $candidate) {
        if (in_array($candidate, $feedbackColumns, true)) { $userColumn = $candidate; break; }
    }
    $feedbackData = ['schedule_id' => $sid];
    if ($userColumn) $feedbackData[$userColumn] = $uid;
    if (in_array('collector_id', $feedbackColumns, true)) $feedbackData['collector_id'] = $sched['collector_id'];
    if (in_array('rating', $feedbackColumns, true)) $feedbackData['rating'] = $rating;
    if (in_array('stars', $feedbackColumns, true)) $feedbackData['stars'] = $rating;
    if (in_array('comment', $feedbackColumns, true)) $feedbackData['comment'] = $comment;
    $fields = array_keys($feedbackData);
    $quotedFields = array_map(static fn($field) => '`' . $field . '`', $fields);
    $updates = [];
    foreach (['rating', 'stars', 'comment'] as $field) {
        if (in_array($field, $fields, true)) $updates[] = "`$field`=VALUES(`$field`)";
    }
    $sql = 'INSERT INTO feedback (' . implode(',', $quotedFields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
    if ($updates) $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $updates);
    $db->prepare($sql)->execute(array_values($feedbackData));
    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
