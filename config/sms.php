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
    if (!SMS_ENABLED || !function_exists('curl_init')) return false;

    // Normalize Philippine mobile numbers to Semaphore's 63XXXXXXXXXX format.
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = '63' . substr($phone, 1);
    } elseif (strlen($phone) === 10 && $phone[0] === '9') {
        $phone = '63' . $phone;
    }
    if (!preg_match('/^639\d{9}$/', $phone) || trim($message) === '') return false;

    $status = 'failed';
    $accepted = false;
    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    if ($ch === false) return false;

    $payload = [
        'apikey'  => SMS_API_KEY,
        'number'  => $phone,
        'message' => $message,
    ];
    // Semaphore uses the registered default sender if sendername is omitted.
    // Only send an explicit sender name when one is configured locally.
    if (trim(SMS_SENDER_NAME) !== '') {
        $payload['sendername'] = trim(SMS_SENDER_NAME);
    }

    try {
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            // Semaphore returns an array of per-message results for this endpoint.
            if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                $providerStatus = strtolower((string) ($result[0]['status'] ?? ''));
                if (in_array($providerStatus, ['queued', 'pending'], true)) {
                    $status = 'pending';
                    $accepted = true;
                } elseif ($providerStatus === 'sent') {
                    $status = 'sent';
                    $accepted = true;
                }
            }
        } elseif ($curlError !== '') {
            error_log('Semaphore SMS request failed: ' . $curlError);
        }
    } catch (Throwable $e) {
        error_log('Semaphore SMS request failed: ' . $e->getMessage());
        if (is_resource($ch) || $ch instanceof CurlHandle) curl_close($ch);
    }

    // Logging must not turn a successfully accepted provider request into a failure.
    try {
        get_db()->prepare("INSERT INTO sms_log (phone,message,status) VALUES (?,?,?)")
            ->execute([$phone, $message, $status]);
    } catch (Throwable $e) {
        error_log('Could not write SMS log: ' . $e->getMessage());
    }

    return $accepted;
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
