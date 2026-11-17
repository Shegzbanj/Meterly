
<?php

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in to continue.', [], 401);
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

try {

    $stmt = $pdo->prepare(
        "SELECT
            b.id,
            b.utility_account_id,
            b.bill_title,
            b.amount,
            b.billing_period,
            b.due_date,
            b.status,
            b.created_at,

            (
                SELECT p.payment_status
                FROM payments p
                WHERE p.bill_id = b.id
                ORDER BY p.id DESC
                LIMIT 1
            ) AS payment_status,

            (
                SELECT p.transaction_reference
                FROM payments p
                WHERE p.bill_id = b.id
                ORDER BY p.id DESC
                LIMIT 1
            ) AS transaction_reference,

            (
                SELECT p.payment_date
                FROM payments p
                WHERE p.bill_id = b.id
                ORDER BY p.id DESC
                LIMIT 1
            ) AS payment_date

        FROM bills b

        INNER JOIN utility_accounts ua
            ON ua.id = b.utility_account_id

        WHERE ua.user_id = ?

        ORDER BY b.due_date ASC, b.id DESC"
    );

    $stmt->execute([$userId]);

    jsonResponse(
        true,
        'Customer bills loaded successfully.',
        ['bills' => $stmt->fetchAll()]
    );

} catch (PDOException $e) {

    error_log('Meterly customer bills error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to load your bills. Please try again.',
        [],
        500
    );
}
