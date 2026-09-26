<?php
// ============================================================
//  DisBasura — SMS Configuration (Semaphore API)
//  Sign up free at: https://semaphore.co (Philippine SMS API)
//  Free plan: 10 free credits to test
// ============================================================

// Keep provider credentials outside the project in ignored config/local.php or environment variables.
$smsLocalConfig = __DIR__ . '/local.php';
if (is_file($smsLocalConfig)) require_once $smsLocalConfig;
$smsApiKey = getenv('SEMAPHORE_API_KEY') ?: (defined('SEMAPHORE_API_KEY') ? SEMAPHORE_API_KEY : (defined('SMS_API_KEY') ? SMS_API_KEY : ''));
$smsSender = getenv('SEMAPHORE_SENDER_NAME') ?: (defined('SEMAPHORE_SENDER_NAME') ? SEMAPHORE_SENDER_NAME : (defined('SMS_SENDER_NAME') ? SMS_SENDER_NAME : 'DisBasura'));
if (!defined('SMS_API_KEY')) define('SMS_API_KEY', $smsApiKey);
if (!defined('SMS_SENDER_NAME')) define('SMS_SENDER_NAME', $smsSender);
if (!defined('SMS_ENABLED')) define('SMS_ENABLED', SMS_API_KEY !== '' && SMS_API_KEY !== 'YOUR_SEMAPHORE_API_KEY_HERE');

function send_sms(string $phone, string $message): bool {
    if (!SMS_ENABLED) return false;

    // Clean phone number — convert 09XX to 639XX
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = '63' . substr($phone, 1);
    }

    try {
        $ch = curl_init('https://api.semaphore.co/api/v4/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'apikey'      => SMS_API_KEY,
                'number'      => $phone,
                'message'     => $message,
                'sendername'  => SMS_SENDER_NAME,
            ]),
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log it
        $db = get_db();
        $db->prepare("INSERT INTO sms_log (phone,message,status) VALUES (?,?,?)")
           ->execute([$phone, $message, $http_code === 200 ? 'sent' : 'failed']);

        return $http_code === 200;
    } catch (Exception $e) {
        return false;
    }
}

function send_sms_to_sitio(string $sitio, string $message): void {
    if (!SMS_ENABLED) return;
    $db = get_db();
    // Sign-up stores phone in `phone`; older imports may have `sms_number` instead.
    $stmt = $db->prepare("SELECT COALESCE(NULLIF(sms_number,''), phone) AS phone_number FROM users WHERE sitio=? AND role IN ('resident','leader') AND COALESCE(NULLIF(sms_number,''), phone) IS NOT NULL AND COALESCE(NULLIF(sms_number,''), phone) != ''");
    $stmt->execute([$sitio]);
    foreach ($stmt->fetchAll() as $user) {
        send_sms($user['phone_number'], $message);
    }
}
