
<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in first.', [], 401);
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Load saved reminder preferences for this customer.
    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT
                b.id AS bill_id,
                COALESCE(p.enabled, 1) AS enabled
             FROM bills b
             INNER JOIN utility_accounts ua
                ON ua.id = b.utility_account_id
             LEFT JOIN bill_reminder_preferences p
                ON p.bill_id = b.id
                AND p.user_id = ua.user_id
             WHERE ua.user_id = ?
             ORDER BY b.id DESC"
        );

        $stmt->execute([$userId]);

        jsonResponse(true, 'Reminder preferences loaded.', [
            'preferences' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    // Save a customer's ON/OFF preference.
    if ($method === 'POST') {
        requirePost();

        $data = getJsonInput();

        $billId = filter_var(
            $data['bill_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $enabled = $data['enabled'] ?? null;

        if (!$billId || $billId < 1) {
            jsonResponse(false, 'Invalid bill ID.', [], 422);
        }

        if (!in_array($enabled, [true, false, 1, 0, '1', '0'], true)) {
            jsonResponse(false, 'Invalid reminder setting.', [], 422);
        }

        // Confirm that this bill belongs to the signed-in customer.
        $check = $pdo->prepare(
            'SELECT b.id
             FROM bills b
             INNER JOIN utility_accounts ua
                ON ua.id = b.utility_account_id
             WHERE b.id = ?
               AND ua.user_id = ?
             LIMIT 1'
        );

        $check->execute([$billId, $userId]);

        if (!$check->fetchColumn()) {
            jsonResponse(false, 'Bill not found for this account.', [], 404);
        }

        $enabledValue = (int) $enabled;

        $save = $pdo->prepare(
            'INSERT INTO bill_reminder_preferences
                (bill_id, user_id, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                enabled = VALUES(enabled)'
        );

        $save->execute([$billId, $userId, $enabledValue]);

        jsonResponse(true, 'Reminder preference saved.', [
            'bill_id' => (int) $billId,
            'enabled' => $enabledValue
        ]);
    }

    jsonResponse(false, 'Method not allowed.', [], 405);

} catch (Throwable $e) {
    error_log('Customer reminder preference error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to save or load reminder preferences.',
        [],
        500
    );
}
