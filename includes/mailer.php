<?php
// ============================================================
//  DisBasura — Email Helper (PHP mail / SMTP via socket)
//  Uses native PHP mail() — works with most XAMPP setups
//  For Gmail SMTP, install PHPMailer via Composer and swap in
// ============================================================

// ─── Email credentials ────────────────────────────────────────
define('MAIL_FROM',     'noreply@disbasura.com');
define('MAIL_FROM_NAME','DisBasura');
// For real SMTP: use PHPMailer. Configure below if needed.
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_USER', 'your@gmail.com');
// define('SMTP_PASS', 'your-app-password');
// define('SMTP_PORT', 587);

function send_reset_email(string $to_email, string $code): bool {
    $subject = 'DisBasura — Password Reset Code';
    $html = <<<HTML
<div style="font-family:sans-serif;max-width:480px;margin:auto;padding:2rem;
            border-radius:12px;border:1px solid #e0ece5">
  <div style="text-align:center;margin-bottom:1.5rem">
    <h1 style="color:#2d8653;font-size:1.6rem;margin:0">DisBasura</h1>
    <p style="color:#4a6358;margin:.25rem 0 0">Smart Garbage Collection System</p>
  </div>
  <h2 style="color:#1a2e22;font-size:1.2rem">Password Reset Code</h2>
  <p style="color:#4a6358">Use the code below to reset your password.
     It expires in <strong>10 minutes</strong>.</p>
  <div style="background:#f4f9f6;border:2px dashed #2d8653;border-radius:12px;
              padding:1.5rem;text-align:center;margin:1.5rem 0">
    <span style="font-size:2.5rem;font-weight:800;letter-spacing:.5rem;
                 color:#2d8653">{$code}</span>
  </div>
  <p style="color:#8baa96;font-size:.85rem">
    If you did not request this, please ignore this email.</p>
</div>
HTML;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";

    return mail($to_email, $subject, $html, $headers);
}
