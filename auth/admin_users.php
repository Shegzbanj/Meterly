
<?php

require_once __DIR__ . '/bootstrap.php';

// Only authenticated administrators may access this endpoint.
if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in first.', [], 401);
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    jsonResponse(false, 'Administrator permission required.', [], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

try {
    // Load registered users without exposing password hashes.
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, role, created_at
         FROM users
         ORDER BY id DESC'
    );

    $stmt->execute();

    $users = $stmt->fetchAll();

    foreach ($users as &$user) {
        $user['id'] = (int) $user['id'];
    }
    unset($user);

    jsonResponse(
        true,
        'Administrator access verified.',
        ['users' => $users]
    );

} catch (PDOException $e) {
    error_log('Meterly admin users error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to verify administrator access.',
        [],
        500
    );
}
