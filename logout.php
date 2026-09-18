<?php
require_once __DIR__ . '/config/session.php';

// Determine role BEFORE clearing anything
$is_admin     = isset($_SESSION['admin_id'])     && !empty($_SESSION['admin_role']);
$is_collector = isset($_SESSION['collector_id']) && !$is_admin;
$is_resident  = isset($_SESSION['user_id'])      && !$is_admin && !$is_collector;

// Clear ALL session data to prevent stale keys from previous logins
session_unset();
session_destroy();

// Redirect to the correct login page
if ($is_admin) {
    header('Location: /disbasura/admin/login.php'); exit;
}
if ($is_collector) {
    header('Location: /disbasura/collector/login.php'); exit;
}

// Resident, leader, or fallback
header('Location: /disbasura/login.php'); exit;
