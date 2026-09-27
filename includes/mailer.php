<?php

function mail_settings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;
    $local = __DIR__ . '/../config/mail.local.php';
    $settings = is_file($local) ? (require $local) : [];
    if (!is_array($settings)) $settings = [];
    $settings['username'] = $settings['username'] ?? (getenv('DISBASURA_SMTP_USER') ?: '');
    $settings['app_password'] = $settings['app_password'] ?? (getenv('DISBASURA_SMTP_APP_PASSWORD') ?: '');
    $settings['from_name'] = $settings['from_name'] ?? 'DisBasura';
    return $settings;
}

function send_disbasura_email(string $to_email, string $to_name, string $subject, string $html, string $plain = ''): bool {
    $settings = mail_settings();
    if (empty($settings['username']) || empty($settings['app_password']) || !is_file(__DIR__ . '/../vendor/autoload.php')) return false;
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = preg_replace('/\s+/', '', $settings['app_password']);
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($settings['username'], $settings['from_name']);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plain !== '' ? $plain : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
        return $mail->send();
    } catch (Throwable $e) {
        error_log('DisBasura email send failed: ' . $e->getMessage());
        return false;
    }
}

function send_contact_verification_email(string $to_email, string $name, string $code): bool {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:20px auto;padding:28px;border:1px solid #dce9e1;border-radius:16px;color:#20372c">'
        . '<h1 style="margin:0;color:#15845c">DisBasura</h1><h2>Confirm your email address</h2>'
        . '<p>Hello ' . $safeName . ', enter this code on the Contact Us page to confirm you can receive messages at this Gmail address.</p>'
        . '<p style="padding:18px;text-align:center;border-radius:12px;background:#edf8f2;color:#087653;font-size:30px;font-weight:bold;letter-spacing:8px">' . $code . '</p>'
        . '<p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p></div>';
    return send_disbasura_email($to_email, $name, 'DisBasura contact email verification', $html, "Your DisBasura verification code is $code. It expires in 10 minutes.");
}

function send_contact_admin_notification(string $name, string $email, ?string $area, string $message): bool {
    $settings = mail_settings();
    $adminEmail = trim((string)($settings['username'] ?? ''));
    if ($adminEmail === '') return false;
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeArea = htmlspecialchars($area ?: 'Not provided', ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:20px auto;padding:28px;border:1px solid #dce9e1;border-radius:16px;color:#20372c">'
        . '<h1 style="margin:0;color:#15845c">DisBasura</h1><h2>New verified Contact Us message</h2>'
        . '<p><strong>From:</strong> ' . $safeName . ' &lt;' . $safeEmail . '&gt;</p>'
        . '<p><strong>Sitio / area:</strong> ' . $safeArea . '</p>'
        . '<div style="padding:18px;border-radius:12px;background:#f3f8f5;line-height:1.6">' . $safeMessage . '</div>'
        . '<p>Open the DisBasura Admin Contact Inbox to review and reply.</p></div>';
    $plain = "New verified Contact Us message\n\nFrom: $name <$email>\nSitio / area: " . ($area ?: 'Not provided') . "\n\n$message\n\nOpen the DisBasura Admin Contact Inbox to review and reply.";
    return send_disbasura_email($adminEmail, 'DisBasura Admin', 'New verified Contact Us message', $html, $plain);
}

function send_admin_setup_verification_email(string $to_email, string $name, string $code): bool {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:20px auto;padding:28px;border:1px solid #dce9e1;border-radius:16px;color:#20372c">'
        . '<h1 style="margin:0;color:#15845c">DisBasura</h1><h2>Verify the first admin email</h2>'
        . '<p>Hello ' . $safeName . ', enter this code on the admin setup page to confirm ownership of this Gmail account.</p>'
        . '<p style="padding:18px;text-align:center;border-radius:12px;background:#edf8f2;color:#087653;font-size:30px;font-weight:bold;letter-spacing:8px">' . $code . '</p>'
        . '<p>This code expires in 10 minutes. Ignore this email if you did not start admin setup.</p></div>';
    return send_disbasura_email($to_email, $name, 'Verify your DisBasura admin Gmail', $html, "Your first-admin verification code is $code. It expires in 10 minutes.");
}

function send_contact_reply_email(string $to_email, string $name, string $reply): bool {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeReply = nl2br(htmlspecialchars($reply, ENT_QUOTES, 'UTF-8'));
    $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:20px auto;padding:28px;border:1px solid #dce9e1;border-radius:16px;color:#20372c">'
        . '<h1 style="margin:0;color:#15845c">DisBasura</h1><h2>Reply to your message</h2>'
        . '<p>Hello ' . $safeName . ',</p><div style="padding:18px;border-radius:12px;background:#f3f8f5;line-height:1.6">' . $safeReply . '</div>'
        . '<p style="color:#718579">You can reply directly to this email if you need further help.</p></div>';
    return send_disbasura_email($to_email, $name, 'Reply from DisBasura', $html, "Hello $name,\n\n$reply\n\nYou can reply directly to this email if you need further help.");
}

function send_reset_email(string $to_email, string $code): bool {
    $subject = 'DisBasura — Password Reset Code';
    $html = <<<HTML
<div style="font-family:sans-serif;max-width:480px;margin:auto;padding:2rem;border-radius:12px;border:1px solid #e0ece5">
  <div style="text-align:center;margin-bottom:1.5rem"><h1 style="color:#2d8653;font-size:1.6rem;margin:0">DisBasura</h1><p style="color:#4a6358;margin:.25rem 0 0">Smart Garbage Collection System</p></div>
  <h2 style="color:#1a2e22;font-size:1.2rem">Password Reset Code</h2>
  <p style="color:#4a6358">Use the code below to reset your password. It expires in <strong>10 minutes</strong>.</p>
  <div style="background:#f4f9f6;border:2px dashed #2d8653;border-radius:12px;padding:1.5rem;text-align:center;margin:1.5rem 0"><span style="font-size:2.5rem;font-weight:800;letter-spacing:.5rem;color:#2d8653">{$code}</span></div>
  <p style="color:#8baa96;font-size:.85rem">If you did not request this, please ignore this email.</p>
</div>
HTML;
    if (send_disbasura_email($to_email, '', $subject, $html)) return true;
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: DisBasura <noreply@disbasura.com>\r\n";
    return mail($to_email, $subject, $html, $headers);
}
