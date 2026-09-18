<?php
// ── Resident / Leader Guard ──────────────────────────────────
// Uses user_id + role — separate from admin and collector.
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['resident','leader'])) {
    header('Location: /disbasura/login.php');
    exit;
}
