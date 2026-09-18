<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
// Works for admin, resident, and leader
$uid = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if (!$uid) { echo json_encode(['count'=>0]); exit; }
$db = get_db();
$s  = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$s->execute([$uid]);
echo json_encode(['count'=>(int)$s->fetchColumn()]);
