<?php
require_once __DIR__ . '/config/session.php';

$role = $_GET['role'] ?? '';

// Clear only THIS role's session (config/session.php already picked the
// right cookie based on ?role=, so this never touches the other two).
session_unset();
session_destroy();

if ($role === 'admin') {
    header('Location: /disbasura/admin/login.php'); exit;
}
if ($role === 'collector') {
    header('Location: /disbasura/collector/login.php'); exit;
}

// Resident, leader, or fallback
header('Location: /disbasura/login.php'); exit;
