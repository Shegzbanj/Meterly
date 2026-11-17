
<?php

require_once __DIR__ . '/bootstrap.php';

// Set the response type to HTML instead of JSON.
header('Content-Type: text/html; charset=UTF-8');

/**
 * Display a customer-friendly payment result page.
 */
function showPaymentResult($title, $message, $success = false, $reference = '')
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeReference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');

    $color = $success ? '#15803d' : '#dc2626';
    $icon = $success ? '✓' : '!';
    $status = $success ? 'Payment confirmed' : 'Payment not confirmed';

    http_response_code($success ? 200 : 400);

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $safeTitle . ' - Meterly</title>
        <style>
            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: #f3f4f6;
                font-family: Arial, sans-serif;
                color: #1f2937;
            }

            .card {
                width: 100%;
                max-width: 460px;
                padding: 35px 28px;
                background: white;
                border-radius: 16px;
                text-align: center;
                box-shadow: 0 8px 30px rgba(0,0,0,.08);
            }

            .icon {
                width: 65px;
                height: 65px;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                background: ' . ($success ? '#dcfce7' : '#fee2e2') . ';
                color: ' . $color . ';
                font-size: 35px;
                font-weight: bold;
            }

            h1 { margin: 0 0 12px; font-size: 25px; }

            .status {
                color: ' . $color . ';
                font-weight: bold;
            }

            p {
                line-height: 1.6;
                color: #4b5563;
            }

            .reference {
                margin: 22px 0;
                padding: 13px;
                background: #f9fafb;
                border-radius: 8px;
                overflow-wrap: anywhere;
                font-size: 13px;
            }

            a {
                display: inline-block;
                margin-top: 12px;
                padding: 13px 24px;
                border-radius: 8px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                font-weight: bold;
            }

            a:hover { background: #1d4ed8; }

            .brand {
                margin-bottom: 22px;
                font-size: 22px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="brand">Meterly</div>
            <div class="icon">' . $icon . '</div>
            <h1>' . $safeTitle . '</h1>
            <p class="status">' . $status . '</p>
            <p>' . $safeMessage . '</p>';

    if ($reference !== '') {
        echo '<div class="reference">
            <strong>Transaction reference</strong><br>
            ' . $safeReference . '
        </div>';
    }

    echo '<a href="../index.html">Return to Meterly</a>
        </main>
    </body>
    </html>';

    exit;
}

// Check that the customer is signed in.
if (empty($_SESSION['user_id'])) {
    showPaymentResult(
        'Session expired',
        'Please sign in to Meterly again and check your payment history before attempting another payment.'
    );
}

$reference = trim($_GET['reference'] ?? '');
$userId = (int) $_SESSION['user_id'];

if ($reference === '' || strlen($reference) > 100) {
    showPaymentResult(
        'Invalid payment reference',
        'The payment reference is missing or invalid.'
    );
}

// Load Paystack configuration.
$configFile = dirname(__DIR__, 2) . '/../private/paystack.php';

if (!is_file($configFile)) {
    showPaymentResult(
        'Configuration error',
        'Payment configuration could not be found. Please contact the administrator.'
    );
}

$config = require $configFile;
$secretKey = $config['secret_key'] ?? '';

if (!$secretKey || !(str_starts_with($secretKey, 'sk_test_') || str_starts_with($secretKey, 'sk_live_'))) {
    showPaymentResult(
        'Configuration error',
        'Paystack secret key is missing or invalid.'
    );
}

try {

    // Confirm that the payment belongs to this customer.
    $stmt = $pdo->prepare(
        "SELECT p.id, p.bill_id, p.amount, p.payment_status,
                b.status AS bill_status
         FROM payments p
         INNER JOIN bills b ON b.id = p.bill_id
         INNER JOIN utility_accounts ua ON ua.id = b.utility_account_id
         WHERE p.transaction_reference = ?
           AND ua.user_id = ?
         LIMIT 1"
    );

    $stmt->execute([$reference, $userId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        showPaymentResult(
            'Payment record not found',
            'We could not find a payment matching this transaction and your account.',
            false,
            $reference
        );
    }

    // Handle a payment that was already verified.
    if (strtolower($payment['payment_status']) === 'successful') {
        showPaymentResult(
            'Payment successful',
            'This payment has already been verified. Your bill is marked as paid.',
            true,
            $reference
        );
    }

    // Verify the transaction directly with Paystack.
    $ch = curl_init(
        'https://api.paystack.co/transaction/verify/' .
        rawurlencode($reference)
    );

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
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
            'Could not contact Paystack for verification.'
        );
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200 || empty($result['status'])) {
        throw new RuntimeException(
            'Paystack could not verify this transaction.'
        );
    }

    $transaction = $result['data'] ?? [];

    // Read Paystack's transaction status first.
    $paystackStatus = strtolower(
        (string) ($transaction['status'] ?? '')
    );

    // Handle failed or abandoned transactions.
    if (in_array($paystackStatus, ['failed', 'abandoned'], true)) {

        $failed = $pdo->prepare(
            "UPDATE payments
             SET payment_status = 'failed'
             WHERE id = ? AND payment_status = 'pending'"
        );

        $failed->execute([$payment['id']]);

        showPaymentResult(
            'Payment failed',
            'Paystack declined or did not complete this transaction. Your bill remains unpaid. You can start a new payment attempt when ready.',
            false,
            $reference
        );
    }

    // Keep incomplete transactions pending.
    if ($paystackStatus !== 'success') {
        showPaymentResult(
            'Payment pending',
            'Paystack has not completed this transaction yet. Your bill remains unpaid. Check your payment history before trying again.',
            false,
            $reference
        );
    }

    // Validate reference, currency and exact amount.
    $expectedAmount = (int) round(
        (float) $payment['amount'] * 100
    );

    if (
        ($transaction['reference'] ?? '') !== $reference ||
        strtoupper((string) ($transaction['currency'] ?? '')) !== 'NGN' ||
        (int) ($transaction['amount'] ?? 0) !== $expectedAmount
    ) {
        throw new RuntimeException(
            'Paystack transaction details did not match the pending payment.'
        );
    }

    // Save the verified payment and update the bill together.
    $pdo->beginTransaction();

    $updatePayment = $pdo->prepare(
        "UPDATE payments
         SET payment_status = 'successful',
             payment_method = 'Paystack',
             payment_date = NOW()
         WHERE id = ? AND payment_status = 'pending'"
    );

    $updatePayment->execute([$payment['id']]);

    // Update the bill only after payment is confirmed.
    if ($updatePayment->rowCount() === 1) {

        $updateBill = $pdo->prepare(
            "UPDATE bills SET status = 'paid' WHERE id = ?"
        );

        $updateBill->execute([$payment['bill_id']]);

    } else {

        // Check whether another request already completed this payment.
        $checkPayment = $pdo->prepare(
            "SELECT payment_status FROM payments WHERE id = ?"
        );

        $checkPayment->execute([$payment['id']]);
        $currentStatus = $checkPayment->fetchColumn();

        if (strtolower((string) $currentStatus) !== 'successful') {
            throw new RuntimeException(
                'The payment record could not be updated.'
            );
        }

        $updateBill = $pdo->prepare(
            "UPDATE bills SET status = 'paid' WHERE id = ?"
        );

        $updateBill->execute([$payment['bill_id']]);
    }

    $pdo->commit();

    showPaymentResult(
        'Payment successful',
        'Paystack has confirmed your payment. Your Meterly bill has now been marked as paid.',
        true,
        $reference
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Meterly Paystack verification error: ' . $e->getMessage()
    );

    showPaymentResult(
        'Unable to verify payment',
        'We could not verify your payment right now. Please check your payment history before trying again.',
        false,
        $reference
    );
}
