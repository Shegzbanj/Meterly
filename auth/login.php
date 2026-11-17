<?php

require_once __DIR__ . '/bootstrap.php';

requirePost();

$data = getJsonInput();

$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 190 ||
    !is_string($password) ||
    $password === ''
) {
    jsonResponse(false, 'Invalid email or password.', [], 401);
}

try {
    /*
     * Rate limit by the direct client IP. Do not trust forwarded-IP headers
     * unless the application is behind a specifically configured proxy.
     * Policy: 10 failed attempts in 15 minutes, then a 15-minute lockout.
     */
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipHash = hash('sha256', $ipAddress);

    $pdo->beginTransaction();

    // Ensure a row exists so it can be safely locked during this request.
    $insertLimit = $pdo->prepare(
        'INSERT IGNORE INTO login_rate_limits
         (ip_hash, failed_attempts, window_started_at, locked_until)
         VALUES (?, 0, NOW(), NULL)'
    );
    $insertLimit->execute([$ipHash]);

    $limitStmt = $pdo->prepare(
        'SELECT failed_attempts, window_started_at, locked_until
         FROM login_rate_limits
         WHERE ip_hash = ?
         FOR UPDATE'
    );
    $limitStmt->execute([$ipHash]);
    $limit = $limitStmt->fetch();

    if (!$limit) {
        throw new RuntimeException('Unable to load login rate limit record.');
    }

    $now = new DateTimeImmutable('now');
    $windowStart = new DateTimeImmutable($limit['window_started_at']);
    $lockedUntil = !empty($limit['locked_until'])
        ? new DateTimeImmutable($limit['locked_until'])
        : null;

    if ($lockedUntil && $lockedUntil > $now) {
        $pdo->commit();
        jsonResponse(
            false,
            'Too many unsuccessful login attempts. Please wait 15 minutes before trying again.',
            [],
            429
        );
    }

    // Start a fresh 15-minute window after the previous one expires.
    if (($now->getTimestamp() - $windowStart->getTimestamp()) >= 900 ||
        ($lockedUntil && $lockedUntil <= $now)) {
        $reset = $pdo->prepare(
            'UPDATE login_rate_limits
             SET failed_attempts = 0,
                 window_started_at = NOW(),
                 locked_until = NULL
             WHERE ip_hash = ?'
        );
        $reset->execute([$ipHash]);
        $limit['failed_attempts'] = 0;
    }

    $pdo->commit();

    // Find the registered user.
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, password_hash, role
         FROM users
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Keep the response generic so the endpoint does not reveal registered emails.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $pdo->beginTransaction();

        $lockStmt = $pdo->prepare(
            'SELECT failed_attempts, window_started_at
             FROM login_rate_limits
             WHERE ip_hash = ?
             FOR UPDATE'
        );
        $lockStmt->execute([$ipHash]);
        $current = $lockStmt->fetch();

        if (!$current) {
            throw new RuntimeException('Unable to update login rate limit record.');
        }

        $currentWindow = new DateTimeImmutable($current['window_started_at']);
        if (($now->getTimestamp() - $currentWindow->getTimestamp()) >= 900) {
            $failedAttempts = 1;
            $update = $pdo->prepare(
                'UPDATE login_rate_limits
                 SET failed_attempts = 1,
                     window_started_at = NOW(),
                     locked_until = NULL
                 WHERE ip_hash = ?'
            );
            $update->execute([$ipHash]);
        } else {
            $failedAttempts = (int) $current['failed_attempts'] + 1;
            $lockout = $failedAttempts >= 10;

            $update = $pdo->prepare(
                'UPDATE login_rate_limits
                 SET failed_attempts = ?,
                     locked_until = ' . ($lockout ? 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)' : 'NULL') . '
                 WHERE ip_hash = ?'
            );
            $update->execute([$failedAttempts, $ipHash]);
        }

        $pdo->commit();

        if ($failedAttempts >= 10) {
            jsonResponse(
                false,
                'Too many unsuccessful login attempts. Please wait 15 minutes before trying again.',
                [],
                429
            );
        }

        jsonResponse(false, 'Invalid email or password.', [], 401);
    }

    // Upgrade the password hash if PHP recommends it.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare(
            'UPDATE users SET password_hash = ? WHERE id = ?'
        );
        $update->execute([$newHash, $user['id']]);
    }

    // Successful login clears failed attempts for this IP.
    $clearLimit = $pdo->prepare(
        'DELETE FROM login_rate_limits WHERE ip_hash = ?'
    );
    $clearLimit->execute([$ipHash]);

    // Create a fresh authenticated session.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    jsonResponse(
        true,
        'Login successful.',
        [
            'user' => [
                'id' => (int) $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ]
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Meterly login error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to sign in at this time. Please try again.',
        [],
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Meterly login rate-limit error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to sign in at this time. Please try again.',
        [],
        500
    );
}
