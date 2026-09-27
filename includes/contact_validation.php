<?php

/** Return a Philippine mobile number in canonical +639XXXXXXXXX form, or null. */
function normalize_ph_mobile(string $phone): ?string {
    $digits = preg_replace('/\D+/', '', trim($phone));
    if (strlen($digits) === 11 && substr($digits, 0, 2) === '09') {
        $digits = '63'.substr($digits, 1);
    } elseif (strlen($digits) === 10 && substr($digits, 0, 1) === '9') {
        $digits = '63'.$digits;
    }
    return preg_match('/^639\d{9}$/', $digits) ? '+'.$digits : null;
}

/** Validate an email address and confirm its domain advertises mail or web DNS. */
function normalize_contact_email(string $email): ?string {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    $domain = substr(strrchr($email, '@'), 1);
    if (!$domain || (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A') && !checkdnsrr($domain, 'AAAA'))) return null;
    return $email;
}

/** Contact uniqueness is shared across resident/leader, collector, and admin records. */
function contact_email_exists(PDO $db, string $email, string $exceptTable = '', int $exceptId = 0): bool {
    try { $db->exec("ALTER TABLE collectors ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE administrators ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL"); } catch (Throwable $e) {}
    foreach (['users', 'collectors', 'administrators'] as $table) {
        if ($table === $exceptTable) {
            $sql = "SELECT id FROM `$table` WHERE LOWER(email)=? AND id<>? LIMIT 1";
            $stmt = $db->prepare($sql); $stmt->execute([$email, $exceptId]);
        } else {
            $stmt = $db->prepare("SELECT id FROM `$table` WHERE LOWER(email)=? LIMIT 1"); $stmt->execute([$email]);
        }
        if ($stmt->fetchColumn()) return true;
    }
    return false;
}

function contact_phone_exists(PDO $db, string $canonicalPhone, string $exceptTable = '', int $exceptId = 0): bool {
    try { $db->exec("ALTER TABLE collectors ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE administrators ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL"); } catch (Throwable $e) {}
    foreach (['users', 'collectors', 'administrators'] as $table) {
        $stmt = $db->query("SELECT id,phone FROM `$table` WHERE phone IS NOT NULL AND phone<>''");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($table === $exceptTable && (int)$row['id'] === $exceptId) continue;
            if (normalize_ph_mobile((string)$row['phone']) === $canonicalPhone) return true;
        }
    }
    return false;
}
