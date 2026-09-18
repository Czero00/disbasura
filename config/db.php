<?php
// ============================================================
//  DisBasura — Database Connection (PDO + MySQL/MariaDB)
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'disbasura');
define('DB_USER',    'root');
define('DB_PASS',    '');          // change if your MySQL has a password
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

function now_pht(): string {
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $dt->format('Y-m-d H:i:s');
}

function fmt_date(?string $value): string {
    if (!$value) return '';
    try {
        $dt = new DateTime(str_replace('T', ' ', $value));
        return $dt->format('M d, Y h:i A');
    } catch (Exception $e) {
        return $value;
    }
}
