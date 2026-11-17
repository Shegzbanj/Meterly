
<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only accept POST requests.
requirePost();

// Read submitted email address.
$data = getJsonInput();
$email = strtolower(trim((string)($data['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    jsonResponse(false, 'Enter a valid email address.', [], 422);
}

$genericMessage = 'If an account exists for that email address, a password-reset link will be sent shortly.';

$mailConfigPath = 'C:/xampp/private/meterly_mail.php';

try {

    // Find the user account.
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email
         FROM users
         WHERE email = ?
         LIMIT 1'
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Do not reveal whether an account exists.
    if (!$user) {
        jsonResponse(true, $genericMessage);
    }

    // Prevent repeated requests within 60 seconds.
    $recent = $pdo->prepare(
        'SELECT id
         FROM password_resets
         WHERE user_id = ?
         AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
         AND used_at IS NULL
         LIMIT 1'
    );

    $recent->execute([(int)$user['id']]);

    if ($recent->fetch()) {
        jsonResponse(true, $genericMessage);
    }

    // Check private Gmail configuration.
    if (!is_file($mailConfigPath)) {
        error_log('Meterly reset: Gmail configuration file not found.');
        jsonResponse(false, 'Email service is unavailable. Please try again later.', [], 500);
    }

    $mailConfig = require $mailConfigPath;

    // Validate required Gmail settings.
    foreach (['host', 'port', 'username', 'password', 'from_email'] as $key) {
        if (empty($mailConfig[$key])) {
            throw new RuntimeException('Missing Gmail configuration: ' . $key);
        }
    }

    // Generate a secure random token.
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    // Save the token in the database.
    $pdo->beginTransaction();

    $deleteOld = $pdo->prepare(
        'DELETE FROM password_resets
         WHERE user_id = ? AND used_at IS NULL'
    );

    $deleteOld->execute([(int)$user['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO password_resets
         (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );

    $insert->execute([
        (int)$user['id'],
        $tokenHash
    ]);

    $pdo->commit();

    // Build the reset URL.
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectPath = rtrim(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/meterly/auth/request_password_reset.php'))), '/');
    $resetUrl = $scheme . $host . $projectPath . '/reset_password.php?token=' . urlencode($token);

    // Configure PHPMailer.
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];

    // Remove accidental spaces from Gmail app passwords.
    $mail->Password = preg_replace(
        '/\s+/',
        '',
        (string)$mailConfig['password']
    );

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int)$mailConfig['port'];
    $mail->Timeout = 25;
    $mail->CharSet = 'UTF-8';

    // Sender.
    $mail->setFrom(
        $mailConfig['from_email'],
        $mailConfig['from_name'] ?? 'Meterly'
    );

    // Recipient.
    $mail->addAddress(
        $user['email'],
        $user['full_name']
    );

    // Prepare email.
    $safeName = htmlspecialchars(
        $user['full_name'],
        ENT_QUOTES,
        'UTF-8'
    );

    $safeUrl = htmlspecialchars(
        $resetUrl,
        ENT_QUOTES,
        'UTF-8'
    );

    $mail->isHTML(true);
    $mail->Subject = 'Reset your Meterly password';

    $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#18241f;line-height:1.6">
            <h2>Reset your Meterly password</h2>

            <p>Hello ' . $safeName . ',</p>

            <p>
                We received a request to reset your Meterly password.
                Click the button below to create a new password.
            </p>

            <p>
                <a href="' . $safeUrl . '"
                   style="display:inline-block;background:#2f7654;color:white;padding:13px 22px;border-radius:8px;text-decoration:none;font-weight:bold">
                    Reset password
                </a>
            </p>

            <p>This link expires in 30 minutes.</p>

            <p>If you did not request this, you can ignore this email.</p>
        </div>
    ';

    $mail->AltBody =
        "Hello " . $user['full_name'] . ",\n\n" .
        "Reset your Meterly password using this link:\n" .
        $resetUrl . "\n\n" .
        "This link expires in 30 minutes.";

    // Send the email.
    $mail->send();

    jsonResponse(true, $genericMessage);

} catch (Throwable $e) {

    // Roll back an active database transaction.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the actual error privately.
    error_log(
        'Meterly password reset error: ' . $e->getMessage()
    );

    // Do not expose technical details to users.
    jsonResponse(
        false,
        'We could not send the reset email. Please try again later.',
        [],
        500
    );
}
