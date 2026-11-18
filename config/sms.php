<?php
// ============================================================
//  DisBasura — SMS Configuration (TextBee)
//  This file defines the shared send_sms() function used by the application.
//  TextBee sends SMS through an Android phone connected to its gateway.
// ============================================================

// Load private local settings (including the TextBee key) when that file exists.
// Alternatively, provide TEXTBEE_API_KEY as a server environment variable.
$smsLocalConfig = __DIR__ . '/local.php';
if (is_file($smsLocalConfig)) require_once $smsLocalConfig;

// Read the key from the environment first, then from a constant defined in local.php.
$textBeeApiKey = getenv('TEXTBEE_API_KEY') ?: (defined('TEXTBEE_API_KEY') ? TEXTBEE_API_KEY : '');
// Define the constant only if another included file has not already defined it.
if (!defined('TEXTBEE_API_KEY')) define('TEXTBEE_API_KEY', $textBeeApiKey);
// Enable sending only when a real key is configured; the example placeholder is disabled.
if (!defined('SMS_ENABLED')) define('SMS_ENABLED', TEXTBEE_API_KEY !== '' && TEXTBEE_API_KEY !== 'YOUR_TEXTBEE_API_KEY_HERE');

// Send one SMS. Returns true when TextBee accepts the message batch, false on failure.
function send_sms(string $phone, string $message): bool {
    // Stop if SMS is disabled or PHP's cURL extension is unavailable.
    if (!SMS_ENABLED || !function_exists('curl_init')) return false;

    // TextBee sends from the gateway SIM's number, so prefix the message with the app name.
    $message = trim($message);
    if ($message !== '' && !preg_match('/^DisBasura\s*:/i', $message)) {
        $message = 'DisBasura: ' . $message;
    }

    // Remove punctuation/spaces, then convert 09XXXXXXXXX or 9XXXXXXXXX to country code 63.
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = '63' . substr($phone, 1);
    } elseif (strlen($phone) === 10 && $phone[0] === '9') {
        $phone = '63' . $phone;
    }
    // Accept only Philippine mobile numbers (639 followed by nine digits) and non-empty messages.
    if (!preg_match('/^639\d{9}$/', $phone) || trim($message) === '') return false;

    // Track whether TextBee accepted the request; final cleanup below handles errors safely.
    $status = 'failed';
    $accepted = false;
    // Create the HTTP request to TextBee's send-SMS endpoint.
    $ch = curl_init('https://api.textbee.dev/api/v1/gateway/send-sms');
    if ($ch === false) return false;

    try {
        // Build the JSON body: one recipient in international format and the SMS text.
        $payload = json_encode([
            'recipients' => ['+' . $phone],
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Configure an HTTP POST with JSON, the API-key header, and connection time limits.
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . TEXTBEE_API_KEY,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $ch = null;

        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            $result = json_decode($response, true);
            // HTTP success means TextBee accepted the batch; it does not confirm handset delivery.
            // TextBee currently wraps send results in `data` (with a root-level fallback for compatibility).
            $sendResult = is_array($result) && isset($result['data']) && is_array($result['data'])
                ? $result['data']
                : $result;
            if (is_array($sendResult) && (
                !empty($sendResult['smsBatchId']) ||
                !empty($sendResult['success']) ||
                (int)($sendResult['recipientCount'] ?? 0) > 0 ||
                (int)($sendResult['successCount'] ?? 0) > 0
            )) {
                $status = 'pending';
                $accepted = true;
            }
        } elseif ($curlError !== '') {
            error_log('TextBee SMS request failed: ' . $curlError);
        } else {
            error_log('TextBee SMS request failed with HTTP status ' . $httpCode);
        }
    } catch (Throwable $e) {
        error_log('TextBee SMS request failed: ' . $e->getMessage());
        if ($ch !== null) curl_close($ch);
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
