<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
$username = trim($_GET['username'] ?? '');
if (!$username) { echo json_encode(['available'=>false,'error'=>'Enter a username']); exit; }
if (strlen($username) < 3) { echo json_encode(['available'=>false,'error'=>'Too short (min 3)']); exit; }
if (!preg_match('/^RT-/i', $username)) { echo json_encode(['available'=>false,'error'=>'Resident usernames must start with RT-']); exit; }
if (!preg_match('/^RT-[a-zA-Z0-9_]+$/', $username)) { echo json_encode(['available'=>false,'error'=>'After RT-, use letters, numbers and underscores only']); exit; }
if (ctype_digit($username)) { echo json_encode(['available'=>false,'error'=>'Cannot be numbers only']); exit; }
$db = get_db();
$s = $db->prepare("SELECT id FROM users WHERE username=?");
$s->execute([$username]);
if ($s->fetch()) { echo json_encode(['available'=>false,'error'=>'Username already taken']); exit; }
$s = $db->prepare("SELECT id FROM administrators WHERE username=?");
$s->execute([$username]);
if ($s->fetch()) { echo json_encode(['available'=>false,'error'=>'Username already taken']); exit; }
$s = $db->prepare("SELECT id FROM collectors WHERE username=?");
$s->execute([$username]);
if ($s->fetch()) { echo json_encode(['available'=>false,'error'=>'Username already taken']); exit; }
echo json_encode(['available'=>true,'message'=>'Username available!']);
