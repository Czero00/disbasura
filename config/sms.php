<?php
// ============================================================
//  DisBasura — SMS Configuration (Semaphore API)
//  Sign up free at: https://semaphore.co (Philippine SMS API)
//  Free plan: 10 free credits to test
// ============================================================

define('SMS_ENABLED', false);         // Set to true when you have API key
define('SMS_API_KEY', 'YOUR_SEMAPHORE_API_KEY_HERE');
define('SMS_SENDER_NAME', 'DisBasura'); // Max 11 chars, registered in Semaphore

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
    $stmt = $db->prepare("SELECT sms_number FROM users WHERE sitio=? AND role IN ('resident','leader') AND sms_number IS NOT NULL AND sms_number != ''");
    $stmt->execute([$sitio]);
    foreach ($stmt->fetchAll() as $user) {
        send_sms($user['sms_number'], $message);
    }
}
