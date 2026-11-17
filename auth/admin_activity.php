<?php
require_once __DIR__ . '/bootstrap.php';
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') jsonResponse(false, 'Administrator access required.', [], 403);
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT UNSIGNED NOT NULL,
        action VARCHAR(120) NOT NULL,
        details VARCHAR(1000) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_admin_activity_created (created_at),
        CONSTRAINT fk_admin_activity_user FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query('SELECT a.id, a.action, a.details, a.created_at, u.email AS actor_email FROM admin_activity a INNER JOIN users u ON u.id = a.admin_user_id ORDER BY a.id DESC LIMIT 500');
        jsonResponse(true, 'Activity log loaded.', ['activities' => $stmt->fetchAll()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.', [], 405);
    $data = getJsonInput();
    $action = trim((string)($data['action'] ?? ''));
    $details = trim((string)($data['details'] ?? ''));
    if ($action === '' || strlen($action) > 120 || strlen($details) > 1000) jsonResponse(false, 'Enter a valid activity and details (maximum 1,000 characters).', [], 422);
    $stmt = $pdo->prepare('INSERT INTO admin_activity (admin_user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([(int)$_SESSION['user_id'], $action, $details]);
    jsonResponse(true, 'Activity saved.', ['id' => (int)$pdo->lastInsertId()], 201);
} catch (Throwable $e) {
    error_log('Meterly admin activity error: ' . $e->getMessage());
    jsonResponse(false, 'Unable to process the activity log.', [], 500);
}
