<?php
require_once __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(false, 'Method not allowed.', [], 405);
if (empty($_SESSION['user_id'])) jsonResponse(false, 'Not signed in.', [], 401);
try {
    $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        $_SESSION = [];
        session_destroy();
        jsonResponse(false, 'Your session is no longer valid. Please sign in again.', [], 401);
    }
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    jsonResponse(true, 'Session restored.', ['user' => [
        'id' => (int) $user['id'], 'full_name' => $user['full_name'],
        'email' => $user['email'], 'role' => $user['role']
    ]]);
} catch (Throwable $e) {
    error_log('Meterly session restore error: ' . $e->getMessage());
    jsonResponse(false, 'Unable to restore your session.', [], 500);
}
