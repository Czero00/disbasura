<?php
require_once __DIR__ . '/../config/db.php';

function ensure_contact_messages_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        area VARCHAR(160) NULL,
        message TEXT NOT NULL,
        verification_code_hash VARCHAR(255) NULL,
        verification_token_hash CHAR(64) NULL,
        verification_expires_at DATETIME NULL,
        verification_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        verified_at DATETIME NULL,
        admin_reply TEXT NULL,
        replied_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_contact_messages_created_at (created_at),
        INDEX idx_contact_messages_verified (email_verified, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $existing = array_column($db->query('SHOW COLUMNS FROM contact_messages')->fetchAll(), 'Field');
    $columns = [
        'verification_code_hash' => 'VARCHAR(255) NULL',
        'verification_token_hash' => 'CHAR(64) NULL',
        'verification_expires_at' => 'DATETIME NULL',
        'verification_attempts' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'email_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'verified_at' => 'DATETIME NULL',
        'admin_reply' => 'TEXT NULL',
        'replied_at' => 'DATETIME NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) $db->exec("ALTER TABLE contact_messages ADD COLUMN `$name` $definition");
    }
}
