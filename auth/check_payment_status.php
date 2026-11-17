<?php

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in to check your payment.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$reference = trim((string) ($input['reference'] ?? ''));
$userId = (int) $_SESSION['user_id'];

if ($reference === '' || strlen($reference) > 100) {
    jsonResponse(false, 'A valid transaction reference is required.', [], 422);
}

$configFile = dirname(__DIR__, 2) . '/../private/paystack.php';
if (!is_file($configFile)) {
    jsonResponse(false, 'Payment configuration could not be found.', [], 500);
}

$config = require $configFile;
$secretKey = $config['secret_key'] ?? '';

if (!$secretKey || !(str_starts_with($secretKey, 'sk_test_') || str_starts_with($secretKey, 'sk_live_'))) {
    jsonResponse(false, 'Paystack secret key is missing or invalid.', [], 500);
}

try {
    // Only allow the signed-in customer to check a payment attached to their own bill.
    $stmt = $pdo->prepare(
        "SELECT p.id, p.bill_id, p.amount, p.payment_status
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
        jsonResponse(false, 'No matching payment was found in your account.', [], 404);
    }

    $currentStatus = strtolower((string) $payment['payment_status']);
    if ($currentStatus === 'successful') {
        jsonResponse(true, 'This payment is already confirmed as successful.', [
            'status' => 'successful',
            'reference' => $reference
        ]);
    }
    if ($currentStatus === 'failed') {
        jsonResponse(true, 'Paystack has already recorded this payment as failed. Your bill remains unpaid.', [
            'status' => 'failed',
            'reference' => $reference
        ]);
    }

    $ch = curl_init(
        'https://api.paystack.co/transaction/verify/' . rawurlencode($reference)
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
        throw new RuntimeException('Could not contact Paystack. Please try again.');
    }

    $result = json_decode($response, true);
    if ($httpCode !== 200 || empty($result['status'])) {
        throw new RuntimeException('Paystack could not verify this transaction.');
    }

    $transaction = $result['data'] ?? [];
    $paystackReference = (string) ($transaction['reference'] ?? '');
    $paystackStatus = strtolower((string) ($transaction['status'] ?? ''));

    if ($paystackReference !== $reference) {
        throw new RuntimeException('The transaction reference returned by Paystack did not match.');
    }

    if (in_array($paystackStatus, ['failed', 'abandoned'], true)) {
        $update = $pdo->prepare(
            "UPDATE payments
             SET payment_status = 'failed',
                 payment_method = 'Paystack'
             WHERE id = ? AND payment_status = 'pending'"
        );
        $update->execute([$payment['id']]);

        jsonResponse(true, 'Paystack confirms this payment failed. Your bill remains unpaid.', [
            'status' => 'failed',
            'reference' => $reference
        ]);
    }

    if ($paystackStatus !== 'success') {
        jsonResponse(true, 'Paystack has not completed this transaction yet. Its status remains pending; your bill is still unpaid.', [
            'status' => 'pending',
            'reference' => $reference
        ]);
    }

    // Never mark a bill paid unless Paystack confirms the exact NGN amount.
    $expectedAmount = (int) round((float) $payment['amount'] * 100);
    if (
        strtoupper((string) ($transaction['currency'] ?? '')) !== 'NGN' ||
        (int) ($transaction['amount'] ?? 0) !== $expectedAmount
    ) {
        throw new RuntimeException('The verified transaction amount or currency does not match this bill.');
    }

    $pdo->beginTransaction();

    $updatePayment = $pdo->prepare(
        "UPDATE payments
         SET payment_status = 'successful',
             payment_method = 'Paystack',
             payment_date = NOW()
         WHERE id = ? AND payment_status = 'pending'"
    );
    $updatePayment->execute([$payment['id']]);

    if ($updatePayment->rowCount() === 1) {
        $updateBill = $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
        $updateBill->execute([$payment['bill_id']]);
    } else {
        $check = $pdo->prepare("SELECT payment_status FROM payments WHERE id = ?");
        $check->execute([$payment['id']]);
        if (strtolower((string) $check->fetchColumn()) !== 'successful') {
            throw new RuntimeException('The payment record could not be updated.');
        }
        $updateBill = $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
        $updateBill->execute([$payment['bill_id']]);
    }

    $pdo->commit();

    jsonResponse(true, 'Paystack confirms this payment was successful. Your bill has been marked as paid.', [
        'status' => 'successful',
        'reference' => $reference
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Meterly payment status check error: ' . $e->getMessage());
    jsonResponse(false, 'Unable to check this payment right now. Please try again.', [], 500);
}
