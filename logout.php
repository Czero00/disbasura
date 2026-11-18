<?php
require_once __DIR__ . '/config/session.php';

$role = $_GET['role'] ?? '';

// Clear only this role's session (config/session.php selects its cookie from ?role=).
session_unset();
session_destroy();

// All roles return to the public landing page after signing out.
header('Location: /disbasura/');
exit;
