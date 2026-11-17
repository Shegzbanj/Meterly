
<?php

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in to continue.', [], 401);
}

requirePost();

// Load Paystack configuration.
$paystackConfigFile = dirname(__DIR__, 2) . '/../private/paystack.php';

if (!file_exists($paystackConfigFile)) {
    jsonResponse(false, 'Paystack configuration file was not found.', [], 500);
}

$paystack = require $paystackConfigFile;
$secretKey = $paystack['secret_key'] ?? '';

if (!$secretKey || !str_starts_with($secretKey, 'sk_')) {
    jsonResponse(false, 'Paystack secret key is not configured correctly.', [], 500);
}

$data = getJsonInput();
$billId = filter_var($data['bill_id'] ?? null, FILTER_VALIDATE_INT);
$userId = (int) $_SESSION['user_id'];

if (!$billId || $billId < 1) {
    jsonResponse(false, 'Please select a valid bill.', [], 400);
}

$reference = null;
$paymentCreated = false;

try {

    /*
     * Start a database transaction.
     * Lock the bill row to prevent simultaneous payment requests.
     */
    $pdo->beginTransaction();

    // Confirm that this bill belongs to the signed-in customer.
    $stmt = $pdo->prepare(
        "SELECT b.id, b.amount, b.status, u.email
         FROM bills b
         INNER JOIN utility_accounts ua
             ON ua.id = b.utility_account_id
         INNER JOIN users u
             ON u.id = ua.user_id
         WHERE b.id = ? AND ua.user_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$billId, $userId]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        $pdo->rollBack();

        jsonResponse(
            false,
            'Bill not found or access denied.',
            [],
            404
        );
    }

    // Prevent payment of an already-paid bill.
    if (strtolower($bill['status']) === 'paid') {
        $pdo->rollBack();

        jsonResponse(
            false,
            'This bill has already been paid.',
            [],
            400
        );
    }

    // Check for an existing pending payment.
    $pendingStmt = $pdo->prepare(
        "SELECT id, transaction_reference
         FROM payments
         WHERE bill_id = ?
           AND payment_status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );

    $pendingStmt->execute([$billId]);
    $existingPayment = $pendingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPayment) {

        $existingReference = $existingPayment['transaction_reference'];

        $pdo->rollBack();

        jsonResponse(
            false,
            'A payment is already pending for this bill. Complete or verify the existing transaction before starting another payment. Reference: ' . $existingReference,
            [
                'reference' => $existingReference,
                'verification_url' =>
                    'auth/verify_payment.php?reference=' .
                    rawurlencode($existingReference)
            ],
            409
        );
    }

    $amount = (float) $bill['amount'];

    if ($amount <= 0) {
        $pdo->rollBack();

        jsonResponse(
            false,
            'Invalid bill amount.',
            [],
            400
        );
    }

    // Paystack accepts amounts in kobo.
    $amountInKobo = (int) round($amount * 100);

    $reference = 'MTR_' . bin2hex(random_bytes(12));

    // Create a pending payment record.
    $insert = $pdo->prepare(
        "INSERT INTO payments
         (
             bill_id,
             amount,
             payment_method,
             payment_status,
             transaction_reference
         )
         VALUES (?, ?, 'Paystack', 'pending', ?)"
    );

    $insert->execute([
        $billId,
        $amount,
        $reference
    ]);

    // Commit the pending payment before contacting Paystack.
    $pdo->commit();

    $paymentCreated = true;

    // Prepare Paystack transaction.
    $payload = [
        'email' => $bill['email'],
        'amount' => $amountInKobo,
        'reference' => $reference,
        'callback_url' =>
            ((isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https://' : 'http://') .
            ($_SERVER['HTTP_HOST'] ?? 'localhost') .
            rtrim(dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/meterly/auth/initialize_payment.php'))), '/') .
            '/auth/verify_payment.php'
    ];

    $ch = curl_init(
        'https://api.paystack.co/transaction/initialize'
    );

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $curlError) {
        throw new RuntimeException(
            'Unable to contact Paystack.'
        );
    }

    $result = json_decode($response, true);

    if (
        $httpCode !== 200 ||
        empty($result['status']) ||
        empty($result['data']['authorization_url'])
    ) {
        throw new RuntimeException(
            $result['message'] ??
            'Paystack could not initialize the payment.'
        );
    }

    // Return the Paystack checkout URL.
    jsonResponse(
        true,
        'Payment initialized successfully.',
        [
            'authorization_url' =>
                $result['data']['authorization_url'],
            'reference' => $reference
        ]
    );

} catch (Throwable $e) {

    // Roll back if a database transaction is still active.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // If Paystack initialization failed, release the pending record.
    if ($paymentCreated && $reference !== null) {
        try {
            $failedStmt = $pdo->prepare(
                "UPDATE payments
                 SET payment_status = 'failed'
                 WHERE transaction_reference = ?
                   AND payment_status = 'pending'"
            );

            $failedStmt->execute([$reference]);

        } catch (Throwable $updateError) {
            error_log(
                'Meterly failed-payment cleanup error: ' .
                $updateError->getMessage()
            );
        }
    }

    error_log(
        'Meterly payment initialization error: ' .
        $e->getMessage()
    );

    jsonResponse(
        false,
        'Unable to initialize payment. Please try again.',
        [],
        500
    );
}
