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
// Check schedule exists and is completed and in user's sitio
$sched = $db->query("SELECT * FROM schedules WHERE id=$sid AND status='completed'")->fetch();
if (!$sched) { echo json_encode(['ok'=>false,'error'=>'Schedule not found']); exit; }

try {
    $db->prepare("INSERT INTO feedback (schedule_id,user_id,collector_id,rating,comment) VALUES (?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment)")
       ->execute([$sid, $uid, $sched['collector_id'], $rating, $comment]);
    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
