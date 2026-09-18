<?php
// ── Admin Guard ──────────────────────────────────────────────
// Uses admin_id + admin_role — completely separate from
// resident (user_id/role) and collector (collector_id) sessions.
// Visiting resident or collector pages will NEVER destroy this.
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: /disbasura/admin/login.php');
    exit;
}
