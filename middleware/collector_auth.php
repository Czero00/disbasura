<?php
// ── Collector Guard ──────────────────────────────────────────
// Uses collector_id — completely separate from admin and resident.
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['collector_id'])) {
    header('Location: /disbasura/collector/login.php');
    exit;
}
