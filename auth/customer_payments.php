
<?php

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in to continue.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT
            p.id,
            p.bill_id,
            p.amount,
            p.payment_method,
            p.payment_status,
            p.transaction_reference,
            p.payment_date,
            p.created_at,
            b.bill_title,
            ua.utility_type,
            ua.provider_name,
            ua.account_number,
            u.full_name AS customer_name,
            u.email AS customer_email

        FROM payments p

        INNER JOIN bills b
            ON b.id = p.bill_id

        INNER JOIN utility_accounts ua
            ON ua.id = b.utility_account_id

        INNER JOIN users u
            ON u.id = ua.user_id

        WHERE ua.user_id = ?

        ORDER BY p.id DESC"
    );

    $stmt->execute([$userId]);

    $payments = [];

    foreach ($stmt->fetchAll() as $payment) {
        $payments[] = [
            'id' => (int) $payment['id'],
            'bill_id' => (int) $payment['bill_id'],
            'amount' => (float) $payment['amount'],
            'status' => strtoupper($payment['payment_status']),
            'reference' => $payment['transaction_reference'],
            'billTitle' => $payment['bill_title'],
            'type' => $payment['utility_type'],
            'provider' => $payment['provider_name'],
            'accountNumber' => $payment['account_number'],
            'method' => $payment['payment_method'],
            'customer' => $payment['customer_email'],
            'customer_name' => $payment['customer_name'],
            'createdAt' => $payment['payment_date']
                ?: $payment['created_at'],
            'isDatabasePayment' => true
        ];
    }

    jsonResponse(
        true,
        'Payment records loaded successfully.',
        ['payments' => $payments]
    );

} catch (PDOException $e) {
    error_log('Meterly customer payments error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to load payment records. Please try again.',
        [],
        500
    );
}
