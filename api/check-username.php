<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
$username = trim($_GET['username'] ?? '');
if (!$username) { echo json_encode(['available'=>false,'error'=>'Enter a username']); exit; }
if (strlen($username) < 3) { echo json_encode(['available'=>false,'error'=>'Too short (min 3)']); exit; }
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) { echo json_encode(['available'=>false,'error'=>'Letters, numbers & _ only']); exit; }
if (ctype_digit($username)) { echo json_encode(['available'=>false,'error'=>'Cannot be numbers only']); exit; }
$db = get_db();
$s = $db->prepare("SELECT id FROM users WHERE username=?");
$s->execute([$username]);
if ($s->fetch()) { echo json_encode(['available'=>false,'error'=>'Username already taken']); exit; }
$s = $db->prepare("SELECT id FROM administrators WHERE username=?");
$s->execute([$username]);
if ($s->fetch()) { echo json_encode(['available'=>false,'error'=>'Username already taken']); exit; }
echo json_encode(['available'=>true,'message'=>'Username available!']);
