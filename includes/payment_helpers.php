<?php
// Shared schema and configuration helpers for special-pickup payments.
require_once __DIR__ . '/../config/db.php';

function ensure_pickup_payment_tables(PDO $db): void {
    static $ready = false;
    if ($ready) return;

    $db->exec("CREATE TABLE IF NOT EXISTS pickup_payment_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        allow_qrph TINYINT(1) NOT NULL DEFAULT 1,
        allow_cash TINYINT(1) NOT NULL DEFAULT 1,
        merchant_name VARCHAR(160) NOT NULL DEFAULT '',
        instructions TEXT NULL,
        qr_image VARCHAR(500) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->exec("INSERT IGNORE INTO pickup_payment_settings (id) VALUES (1)");

    $db->exec("CREATE TABLE IF NOT EXISTS pickup_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        resident_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('qrph','cash') NOT NULL,
        reference_number VARCHAR(120) NULL,
        proof_photo VARCHAR(500) NULL,
        status ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
        admin_note VARCHAR(500) NULL,
        reviewed_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_pickup_payments_request (request_id, id),
        INDEX idx_pickup_payments_status (status, created_at),
        CONSTRAINT fk_pickup_payments_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        CONSTRAINT fk_pickup_payments_resident FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function get_pickup_payment_settings(PDO $db): array {
    ensure_pickup_payment_tables($db);
    return $db->query('SELECT * FROM pickup_payment_settings WHERE id=1')->fetch() ?: [
        'enabled' => 0, 'fee' => 0, 'allow_qrph' => 1, 'allow_cash' => 1,
        'merchant_name' => '', 'instructions' => '', 'qr_image' => null,
    ];
}

function get_latest_pickup_payment(PDO $db, int $request_id): ?array {
    ensure_pickup_payment_tables($db);
    $stmt = $db->prepare('SELECT * FROM pickup_payments WHERE request_id=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$request_id]);
    return $stmt->fetch() ?: null;
}

function is_pickup_payment_required(array $settings): bool {
    return !empty($settings['enabled']) && (float)($settings['fee'] ?? 0) > 0;
}
