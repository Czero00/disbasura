<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) { echo json_encode(['ok'=>false]); exit; }
$db = get_db();
auto_flag_missed($db);
echo json_encode(['ok'=>true]);
