
<?php

require_once __DIR__ . '/bootstrap.php';

// Only authenticated customers can access their accounts.
if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Please sign in to continue.', [], 401);
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {

    // GET: Load accounts belonging to the signed-in customer.
    if ($method === 'GET') {

        $stmt = $pdo->prepare(
            'SELECT id, utility_type, provider_name, account_number, created_at
             FROM utility_accounts
             WHERE user_id = ?
             ORDER BY id DESC'
        );

        $stmt->execute([$userId]);

        jsonResponse(
            true,
            'Utility accounts loaded successfully.',
            ['accounts' => $stmt->fetchAll()]
        );
    }

    // POST: Save a new utility account.
    if ($method === 'POST') {

        $data = getJsonInput();

        $provider = trim($data['provider_name'] ?? '');
        $accountNumber = trim($data['account_number'] ?? '');
        $utilityType = strtolower(trim($data['utility_type'] ?? ''));

        $allowedTypes = [
            'electricity',
            'water',
            'internet',
            'other'
        ];

        if (
            $provider === '' ||
            strlen($provider) > 150
        ) {
            jsonResponse(false, 'Enter a valid provider name.', [], 422);
        }

        if (
            $accountNumber === '' ||
            strlen($accountNumber) > 100
        ) {
            jsonResponse(false, 'Enter a valid account number.', [], 422);
        }

        if (!in_array($utilityType, $allowedTypes, true)) {
            jsonResponse(false, 'Select a valid utility type.', [], 422);
        }

        // Prevent duplicate accounts for the same customer.
        $check = $pdo->prepare(
            'SELECT id FROM utility_accounts
             WHERE user_id = ?
             AND utility_type = ?
             AND provider_name = ?
             AND account_number = ?
             LIMIT 1'
        );

        $check->execute([
            $userId,
            $utilityType,
            $provider,
            $accountNumber
        ]);

        if ($check->fetch()) {
            jsonResponse(
                false,
                'This utility account has already been added.',
                [],
                409
            );
        }

        $insert = $pdo->prepare(
            'INSERT INTO utility_accounts
             (user_id, utility_type, provider_name, account_number)
             VALUES (?, ?, ?, ?)'
        );

        $insert->execute([
            $userId,
            $utilityType,
            $provider,
            $accountNumber
        ]);

        jsonResponse(
            true,
            'Utility account saved successfully.',
            [
                'account' => [
                    'id' => (int) $pdo->lastInsertId(),
                    'utility_type' => $utilityType,
                    'provider_name' => $provider,
                    'account_number' => $accountNumber
                ]
            ],
            201
        );
    }

    jsonResponse(false, 'Method not allowed.', [], 405);

} catch (PDOException $e) {

    error_log('Meterly utility account error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Unable to process your utility account request.',
        [],
        500
    );
}
