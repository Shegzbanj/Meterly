<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$message = '';
$success = false;
$validToken = false;

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

if ($token !== '' && strlen($token) === 64 && ctype_xdigit($token)) {
    $check = $pdo->prepare('SELECT id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $check->execute([hash('sha256', $token)]);
    $validToken = (bool)$check->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!$validToken) {
        $message = 'This reset link is invalid, expired, or has already been used. Request a new one from Meterly.';
    } elseif ($password !== $confirm) {
        $message = 'The passwords do not match.';
    } elseif (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $message = 'Use at least 12 characters with an uppercase letter, lowercase letter, number and symbol.';
    } else {
        try {
            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
            $find->execute([hash('sha256', $token)]);
            $reset = $find->fetch();
            if (!$reset) {
                $pdo->rollBack();
                $validToken = false;
                $message = 'This reset link is invalid, expired, or has already been used. Request a new one from Meterly.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $updateUser = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $updateUser->execute([$hash, (int)$reset['user_id']]);
                $mark = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
                $mark->execute([(int)$reset['id']]);
                $deleteOthers = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?');
                $deleteOthers->execute([(int)$reset['user_id'], (int)$reset['id']]);
                $pdo->commit();
                $success = true;
                $validToken = false;
                $message = 'Your password has been changed successfully. You can now sign in to Meterly with your new password.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Meterly password reset completion error: ' . $e->getMessage());
            $message = 'We could not update your password. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset password · Meterly</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f1f5f1;color:#18241f;font-family:Inter,Arial,sans-serif}.card{width:min(100%,480px);background:#fff;border:1px solid #e2e9e2;border-radius:20px;padding:34px;box-shadow:0 16px 50px #1b392012}.brand{font-size:24px;font-weight:800;color:#2f7654;margin-bottom:24px}h1{font-size:27px;margin:0 0 10px}p{line-height:1.6;color:#65736a}.field{margin:18px 0}.field label{display:block;font-weight:650;margin-bottom:8px}.field input{width:100%;padding:14px;border:1px solid #dce5dc;border-radius:10px;font-size:16px}.button{width:100%;padding:14px;border:0;border-radius:10px;background:#2f7654;color:#fff;font-weight:700;font-size:15px;cursor:pointer}.notice{padding:12px;border-radius:9px;background:#f4f7f4;color:#365542}.notice.error{background:#fff2f0;color:#9b3028}.link{display:inline-block;margin-top:18px;color:#2f7654;font-weight:650;text-decoration:none}.requirements{font-size:13px;margin-top:6px}
</style>
</head>
<body><main class="card"><div class="brand">Meterly</div><h1>Choose a new password</h1><p>Enter a strong password for your Meterly account.</p>
<?php if ($message !== ''): ?><p class="notice <?= $success || $validToken ? '' : 'error' ?>" role="status"><?= h($message) ?></p><?php endif; ?>
<?php if ($success): ?><a class="link" href="index.html">Return to sign in</a>
<?php elseif ($validToken): ?>
<form method="post" action="reset_password.php" autocomplete="on">
<input type="hidden" name="token" value="<?= h($token) ?>">
<div class="field"><label for="password">New password</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
<div class="field"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required></div>
<p class="requirements">Use at least 12 characters, including uppercase, lowercase, a number and a symbol.</p>
<button class="button" type="submit">Update password</button>
</form>
<?php else: ?><a class="link" href="index.html#forgot">Request another reset link</a><?php endif; ?>
</main></body></html>
