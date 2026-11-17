<?php

require_once __DIR__ . '/bootstrap.php';

// Administrator authentication.
if (empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin') {
    jsonResponse(false, 'Administrator access required.', [], 403);
}

requirePost();

$data = getJsonInput();
$action = $data['action'] ?? '';

try {

    // DELETE BILL
    if ($action === 'delete') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            jsonResponse(false, 'Invalid bill ID.', [], 422);
        }

        // Keep bills that have payment history for audit purposes.
        $paymentCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM payments WHERE bill_id = ?'
        );
        $paymentCheck->execute([$id]);

        if ((int) $paymentCheck->fetchColumn() > 0) {
            jsonResponse(
                false,
                'This bill cannot be deleted because it has payment history.',
                [],
                409
            );
        }

        $stmt = $pdo->prepare('DELETE FROM bills WHERE id = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(false, 'Bill not found.', [], 404);
        }

        jsonResponse(true, 'Bill deleted successfully.');
    }

    // ONLY CREATE AND UPDATE ARE ALLOWED BELOW.
    if (!in_array($action, ['create', 'update'], true)) {
        jsonResponse(false, 'Invalid bill action.', [], 422);
    }

    $accountId = filter_var(
        $data['utility_account_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $title = trim($data['bill_title'] ?? '');
    $amount = $data['amount'] ?? '';
    $period = trim($data['billing_period'] ?? '');
    $dueDate = trim($data['due_date'] ?? '');
    $status = strtolower(trim($data['status'] ?? ''));

    if (!$accountId || $accountId < 1) {
        jsonResponse(false, 'Select a valid utility account.', [], 422);
    }

    $accountCheck = $pdo->prepare(
        'SELECT id FROM utility_accounts WHERE id = ?'
    );
    $accountCheck->execute([$accountId]);

    if (!$accountCheck->fetch()) {
        jsonResponse(false, 'Selected utility account does not exist.', [], 404);
    }

    if ($title === '' || strlen($title) > 150) {
        jsonResponse(false, 'Enter a valid bill title.', [], 422);
    }

    if (!is_numeric($amount) ||
        !is_finite((float) $amount) ||
        (float) $amount <= 0) {
        jsonResponse(false, 'Enter a valid bill amount.', [], 422);
    }

    if ($period === '' || strlen($period) > 50) {
        jsonResponse(false, 'Enter a valid billing period.', [], 422);
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
    if (!$date || $date->format('Y-m-d') !== $dueDate) {
        jsonResponse(false, 'Enter a valid due date.', [], 422);
    }

    $allowedStatuses = ['unpaid', 'paid', 'overdue'];
    if (!in_array($status, $allowedStatuses, true)) {
        jsonResponse(false, 'Select a valid bill status.', [], 422);
    }

    if ($action === 'create') {

        // A new bill cannot be marked paid without a recorded successful payment.
        if ($status === 'paid') {
            jsonResponse(
                false,
                'A new bill cannot be marked as paid. Record a successful payment first.',
                [],
                422
            );
        }

        $stmt = $pdo->prepare(
            'INSERT INTO bills
             (utility_account_id, bill_title, amount,
              billing_period, due_date, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $accountId,
            $title,
            $amount,
            $period,
            $dueDate,
            $status
        ]);

        jsonResponse(
            true,
            'Bill created successfully.',
            ['bill_id' => (int) $pdo->lastInsertId()],
            201
        );
    }

    if ($action === 'update') {

        $id = filter_var(
            $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            jsonResponse(false, 'Invalid bill ID.', [], 422);
        }

        $check = $pdo->prepare(
            'SELECT id FROM bills WHERE id = ?'
        );
        $check->execute([$id]);

        if (!$check->fetch()) {
            jsonResponse(false, 'Bill not found.', [], 404);
        }

        // Only successful payments count as evidence that a bill is paid.
        $paidCheck = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE bill_id = ? AND LOWER(payment_status) = 'successful'"
        );
        $paidCheck->execute([$id]);
        $successfulTotal = (float) $paidCheck->fetchColumn();

        $billAmount = (float) $amount;
        $isCovered = $successfulTotal + 0.00001 >= $billAmount;

        if ($status === 'paid' && !$isCovered) {
            jsonResponse(
                false,
                'This bill cannot be marked as paid because successful payments do not cover the full bill amount.',
                [
                    'successful_payment_total' => $successfulTotal,
                    'bill_amount' => $billAmount
                ],
                422
            );
        }

        if ($isCovered && $status !== 'paid') {
            jsonResponse(
                false,
                'This bill already has successful payments covering its full amount. Its status must remain paid.',
                [],
                409
            );
        }

        $stmt = $pdo->prepare(
            'UPDATE bills
             SET utility_account_id = ?,
                 bill_title = ?,
                 amount = ?,
                 billing_period = ?,
                 due_date = ?,
                 status = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $accountId,
            $title,
            $amount,
            $period,
            $dueDate,
            $status,
            $id
        ]);

        jsonResponse(true, 'Bill updated successfully.');
    }

} catch (PDOException $e) {

    error_log('Meterly bill management error: ' . $e->getMessage());

    jsonResponse(
        false,
        'The bill could not be saved or deleted. Check whether related payment or reminder records prevent this operation.',
        [],
        500
    );
}
