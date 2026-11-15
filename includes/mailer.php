<?php

// Email through Resend (https://resend.com) and the one-time codes it carries.
// Load it with require_once after config/database.php.

require_once __DIR__ . '/functions.php';

/** The settings from config/app.php, loaded once. */
function app_config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/../config/app.php';
}

/** Send an email with an HTML body and a plain-text fallback. Returns true when Resend accepted it. */
function send_mail(string $to, string $subject, string $html, string $text): bool
{
    $config = app_config();
    if ($config['resend_api_key'] === '') {
        return false;
    }

    $curl = curl_init('https://api.resend.com/emails');
    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $config['resend_api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'from'    => $config['mail_from'],
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ]),
    ]);
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);

    if ($status >= 200 && $status < 300) {
        return true;
    }
    error_log("Resend did not send to $to (HTTP $status): " . ($error !== '' ? $error : (string) $response));
    return false;
}

const CODE_MINUTES = 15;
const CODE_MAX_ATTEMPTS = 5;
const CODE_RESEND_SECONDS = 60;

/**
 * Make a new 6-digit code for a user, store its hash and email it.
 * $purpose is 'verify' (confirm the email) or 'reset' (forgotten password).
 * Returns the toast to show: ['type' => 'success'|'info'|'error', 'message' => ...].
 * 'error' means no code reached the user. On local, when the email cannot be sent,
 * the code itself is shown ('info') so you can keep testing without email.
 */
function send_code(PDO $pdo, array $user, string $purpose): array
{
    $stmt = $pdo->prepare('SELECT code_sent_at > NOW() - INTERVAL ? SECOND FROM users WHERE user_id = ? AND code_purpose = ?');
    $stmt->execute([CODE_RESEND_SECONDS, $user['user_id'], $purpose]);
    if ((int) $stmt->fetchColumn() === 1) {
        return ['type' => 'error', 'message' => 'A code was sent less than a minute ago. Check your inbox and spam folder, or wait a moment.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare(
        'UPDATE users SET code_purpose = ?, code_hash = ?, code_expires_at = NOW() + INTERVAL ? MINUTE,
            code_sent_at = NOW(), code_attempts = 0
         WHERE user_id = ?'
    );
    $stmt->execute([$purpose, password_hash($code, PASSWORD_DEFAULT), CODE_MINUTES, $user['user_id']]);

    $email = code_email($purpose, $user['full_name'], $code);
    if (send_mail($user['email'], $email['subject'], $email['html'], $email['text'])) {
        return ['type' => 'success', 'message' => "We emailed a 6-digit code to {$user['email']}."];
    }
    if (app_config()['env'] === 'local') {
        return ['type' => 'info', 'message' => "Local mode: the email to {$user['email']} was not sent, so here is the code: $code."];
    }
    return ['type' => 'error', 'message' => 'We could not send the email. Try again in a few minutes.'];
}

/**
 * Check a code the user typed. Returns null when it is right (and uses it up), otherwise a message.
 * Each code allows CODE_MAX_ATTEMPTS tries before a new one is needed.
 */
function check_code(PDO $pdo, int $user_id, string $purpose, string $code): ?string
{
    $stmt = $pdo->prepare(
        'SELECT code_hash, code_attempts, code_expires_at > NOW() AS fresh
         FROM users WHERE user_id = ? AND code_purpose = ?'
    );
    $stmt->execute([$user_id, $purpose]);
    $row = $stmt->fetch();

    if (!$row || $row['code_hash'] === null) {
        return 'There is no code waiting. Ask for a new one.';
    }
    if ((int) $row['code_attempts'] >= CODE_MAX_ATTEMPTS) {
        return 'Too many wrong tries. Ask for a new code.';
    }
    if ((int) $row['fresh'] !== 1) {
        return 'That code has expired. Ask for a new one.';
    }
    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare('UPDATE users SET code_attempts = code_attempts + 1 WHERE user_id = ?')->execute([$user_id]);
        return 'That code is wrong. Check the email and try again.';
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET code_purpose = NULL, code_hash = NULL, code_expires_at = NULL, code_sent_at = NULL, code_attempts = 0
         WHERE user_id = ?'
    );
    $stmt->execute([$user_id]);
    return null;
}

/**
 * The subject, HTML and plain text of a code email.
 * Email apps ignore stylesheets, so the HTML uses tables and inline styles.
 * Colours match assets/css/style.css.
 */
function code_email(string $purpose, string $name, string $code): array
{
    $copy = $purpose === 'verify'
        ? [
            'subject' => 'Your Library confirmation code: ' . $code,
            'heading' => 'Confirm your email',
            'intro'   => 'Welcome to the Library. Type this code on the confirmation page to finish creating your account.',
            'ignore'  => 'If you did not sign up, ignore this email. The account stays locked without the code.',
        ]
        : [
            'subject' => 'Your Library password reset code: ' . $code,
            'heading' => 'Reset your password',
            'intro'   => 'Someone asked to reset your Library password. Type this code on the reset page, along with your new password.',
            'ignore'  => 'If you did not ask for this, ignore this email. Your password stays the same.',
        ];
    $minutes = CODE_MINUTES;
    $spaced = substr($code, 0, 3) . ' ' . substr($code, 3);
    $safe_name = e($name);

    $text = "Hello $name,\n\n{$copy['intro']}\n\n    $spaced\n\n"
        . "The code works for $minutes minutes and can only be used once.\n{$copy['ignore']}\n\nLibrary Management System\n";

    $html = <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{$copy['heading']}</title></head>
<body style="margin:0;padding:0;background:#ecebf0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ecebf0;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#f6f5f9;border:1px solid #cfcdd9;border-top:8px solid #d6a54c;">
      <tr><td style="background:#2a2840;padding:16px 28px;font-family:Georgia,'Times New Roman',serif;font-size:22px;font-weight:bold;color:#ecebf3;">Library</td></tr>
      <tr><td style="padding:28px;font-family:Verdana,Arial,sans-serif;font-size:15px;line-height:1.6;color:#22212e;">
        <h1 style="margin:0 0 16px;font-family:Georgia,'Times New Roman',serif;font-size:24px;font-weight:bold;color:#22212e;">{$copy['heading']}</h1>
        <p style="margin:0 0 8px;">Hello {$safe_name},</p>
        <p style="margin:0 0 24px;">{$copy['intro']}</p>
        <p style="margin:0 0 24px;text-align:center;">
          <span style="display:inline-block;padding:14px 24px;background:#ffffff;border:1px dashed #3d3a63;font-family:'Courier New',Courier,monospace;font-size:32px;font-weight:bold;letter-spacing:6px;color:#22212e;">{$spaced}</span>
        </p>
        <p style="margin:0 0 8px;color:#5f5d70;font-size:13px;">The code works for {$minutes} minutes and can only be used once.</p>
        <p style="margin:0;color:#5f5d70;font-size:13px;">{$copy['ignore']}</p>
      </td></tr>
      <tr><td style="padding:16px 28px;border-top:1px solid #cfcdd9;font-family:Verdana,Arial,sans-serif;font-size:12px;color:#5f5d70;">Library Management System. This is an automatic email, so replies are not read.</td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    return ['subject' => $copy['subject'], 'html' => $html, 'text' => $text];
}
