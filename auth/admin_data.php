
<?php

require_once __DIR__ . '/bootstrap.php';

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

    // Keep unpaid bills past their due date accurately classified. Paid bills are never changed.
    $pdo->exec("UPDATE bills SET status = 'overdue' WHERE status = 'unpaid' AND due_date < CURDATE()");

    $users = $pdo->query(
        'SELECT id, full_name, email, role, created_at
         FROM users ORDER BY id DESC'
    )->fetchAll();

    $accounts = $pdo->query(
        'SELECT ua.*, u.full_name AS customer_name,
                u.email AS customer_email
         FROM utility_accounts ua
         INNER JOIN users u ON u.id = ua.user_id
         ORDER BY ua.id DESC'
    )->fetchAll();

    $bills = $pdo->query(
        'SELECT b.*, ua.provider_name, ua.account_number,
                ua.utility_type, u.full_name AS customer_name,
                u.email AS customer_email
         FROM bills b
         INNER JOIN utility_accounts ua
            ON ua.id = b.utility_account_id
         INNER JOIN users u ON u.id = ua.user_id
         ORDER BY b.id DESC'
    )->fetchAll();

    $payments = $pdo->query(
        'SELECT p.*, b.bill_title,
                u.full_name AS customer_name,
                u.email AS customer_email
         FROM payments p
         INNER JOIN bills b ON b.id = p.bill_id
         INNER JOIN utility_accounts ua
            ON ua.id = b.utility_account_id
         INNER JOIN users u ON u.id = ua.user_id
         ORDER BY p.id DESC'
    )->fetchAll();

    $consumption = $pdo->query(
        'SELECT cr.*, ua.provider_name, ua.account_number,
                u.full_name AS customer_name,
                u.email AS customer_email
         FROM consumption_records cr
         INNER JOIN utility_accounts ua
            ON ua.id = cr.utility_account_id
         INNER JOIN users u ON u.id = ua.user_id
         ORDER BY cr.id DESC'
    )->fetchAll();

    $reminders = $pdo->query(
        'SELECT r.*, b.bill_title,
                u.full_name AS customer_name,
                u.email AS customer_email
         FROM reminders r
         INNER JOIN bills b ON b.id = r.bill_id
         INNER JOIN utility_accounts ua
            ON ua.id = b.utility_account_id
         INNER JOIN users u ON u.id = ua.user_id
         ORDER BY r.id DESC'
    )->fetchAll();

    jsonResponse(
        true,
        'Live database records loaded successfully.',
        [
            'data' => [
                'users' => $users,
                'utility_accounts' => $accounts,
                'bills' => $bills,
                'payments' => $payments,
                'consumption_records' => $consumption,
                'reminders' => $reminders
            ]
        ]
    );

} catch (PDOException $e) {

    error_log('Meterly admin data error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to load live database records.',
        [],
        500
    );
}
