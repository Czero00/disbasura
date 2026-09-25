<?php
// ============================================================
//  DisBasura — Shared Helper Functions (Updated v2)
// ============================================================

// db.php defines get_db(), now_pht(), fmt_date() — require_once is safe even
// if middleware already included it; it will NOT run twice.
require_once __DIR__ . '/../config/db.php';

function get_sitios(): array {
    return get_db()->query("SELECT name FROM sitios ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}

// ── Geographic hierarchy (Cities -> Barangays -> Sitios -> Sites) ─
function get_cities(): array {
    return get_db()->query("SELECT * FROM cities ORDER BY name")->fetchAll();
}

function get_barangays(int $city_id = 0): array {
    if ($city_id) {
        $s = get_db()->prepare("SELECT * FROM barangays WHERE city_id=? ORDER BY name");
        $s->execute([$city_id]);
        return $s->fetchAll();
    }
    return get_db()->query("SELECT b.*, c.name AS city_name FROM barangays b JOIN cities c ON b.city_id=c.id ORDER BY c.name, b.name")->fetchAll();
}

function get_sitios_by_barangay(int $barangay_id): array {
    $s = get_db()->prepare("SELECT * FROM sitios WHERE barangay_id=? ORDER BY name");
    $s->execute([$barangay_id]);
    return $s->fetchAll();
}

function get_sites(int $sitio_id): array {
    $s = get_db()->prepare("SELECT * FROM sites WHERE sitio_id=? ORDER BY name");
    $s->execute([$sitio_id]);
    return $s->fetchAll();
}

function get_unread_count(int $user_id): int {
    $s = get_db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->execute([$user_id]);
    return (int)$s->fetchColumn();
}

function ensure_admin_notifications_table(PDO $db): void {
    static $ready = false;
    if ($ready) return;
    $db->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        title VARCHAR(100) NOT NULL DEFAULT 'Admin Alert',
        message TEXT NOT NULL,
        is_read TINYINT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_notifications_unread (admin_id, is_read, created_at),
        CONSTRAINT fk_admin_notifications_admin FOREIGN KEY (admin_id) REFERENCES administrators(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function get_unread_admin_count(int $admin_id): int {
    $db = get_db();
    ensure_admin_notifications_table($db);
    $s = $db->prepare('SELECT COUNT(*) FROM admin_notifications WHERE admin_id = ? AND is_read = 0');
    $s->execute([$admin_id]);
    return (int)$s->fetchColumn();
}

function notify_user(PDO $db, int $user_id, string $message, string $title = 'Notification'): void {
    $db->prepare("INSERT INTO notifications (user_id,title,message,status,created_at) VALUES (?,?,?,?,?)")
       ->execute([$user_id, $title, $message, 'sent', now_pht()]);
}

function notify_all_sitio(PDO $db, string $sitio, string $message, string $title = 'Announcement'): void {
    $stmt = $db->prepare("SELECT id FROM users WHERE sitio=? AND role IN ('resident','leader')");
    $stmt->execute([$sitio]);
    $ins = $db->prepare("INSERT INTO notifications (user_id,title,message,status,created_at) VALUES (?,?,?,?,?)");
    foreach ($stmt->fetchAll() as $r) {
        $ins->execute([$r['id'], $title, $message, 'sent', now_pht()]);
    }
    // SMS notify
    if (file_exists(__DIR__.'/../config/sms.php')) {
        require_once __DIR__.'/../config/sms.php';
        send_sms_to_sitio($sitio, strip_tags($message));
    }
}

function notify_all_admins(PDO $db, string $message, string $title = 'Admin Alert'): void {
    try {
        ensure_admin_notifications_table($db);
        $admins = $db->query('SELECT id FROM administrators')->fetchAll();
        $ins = $db->prepare('INSERT INTO admin_notifications (admin_id,title,message,created_at) VALUES (?,?,?,?)');
        foreach ($admins as $admin) $ins->execute([$admin['id'], $title, $message, now_pht()]);
    } catch (Throwable $e) {
        error_log('Could not create admin notification: ' . $e->getMessage());
    }
}

// ── Activity Log ─────────────────────────────────────────────
// actor_type: 'admin' | 'collector' | 'resident' | 'leader' — matches
// the manuscript's polymorphic actor_id/actor_type design (Table 21),
// so collector/resident actions can be logged too, not just admin's.
function log_activity(int $actor_id, string $action_type, string $details = '', string $actor_type = 'admin', ?string $affected_table = null, ?int $affected_record_id = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        get_db()->prepare("INSERT INTO activity_log (actor_id,actor_type,action_type,details,affected_table,affected_record_id,ip_address,created_at) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$actor_id, $actor_type, $action_type, $details, $affected_table, $affected_record_id, $ip, now_pht()]);
    } catch (Exception $e) {}
}

// ── Auto-Flag Missed Collections ─────────────────────────────
function auto_flag_missed(PDO $db): void {
    $now = now_pht();
    $stmt = $db->prepare("
        UPDATE schedules
        SET status='unverified'
        WHERE status IN ('scheduled','assigned')
        AND scheduled_at < ?
    ");
    $stmt->execute([$now]);
    $flagged = $stmt->rowCount();
    if ($flagged > 0) {
        notify_all_admins($db, "⚠️ $flagged schedule(s) passed their time without being marked as completed. Please verify.");
    }
}

// ── User Preferences ─────────────────────────────────────────
function get_preferences(int $user_id): array {
    try {
        $s = get_db()->prepare("SELECT * FROM user_preferences WHERE user_id=?");
        $s->execute([$user_id]);
        return $s->fetch() ?: ['dark_mode' => 0, 'language' => 'en'];
    } catch (Exception $e) {
        return ['dark_mode' => 0, 'language' => 'en'];
    }
}

function save_preferences(int $user_id, int $dark_mode, string $language): void {
    get_db()->prepare("INSERT INTO user_preferences (user_id,dark_mode,language) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE dark_mode=VALUES(dark_mode), language=VALUES(language), updated_at=NOW()")
        ->execute([$user_id, $dark_mode, $language]);
}

// ── Language ─────────────────────────────────────────────────
function get_lang(int $user_id = 0): array {
    $lang = 'en';
    if ($user_id) {
        $p = get_preferences($user_id);
        $lang = $p['language'] ?? 'en';
    }
    $file = __DIR__.'/../lang/'.$lang.'.php';
    if (!file_exists($file)) $file = __DIR__.'/../lang/en.php';
    return require $file;
}

// ── File Upload ───────────────────────────────────────────────
function save_upload(array $file, string $prefix): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','webp','gif'])) return null;
    $fname = $prefix.'_'.time().'.'.$ext;
    $dest  = __DIR__.'/../uploads/'.$fname;
    return move_uploaded_file($file['tmp_name'], $dest) ? $fname : null;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
