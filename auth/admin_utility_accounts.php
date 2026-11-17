<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

if (empty($_SESSION['user_id'])) {
    respond(401, ['ok' => false, 'message' => 'Please sign in first.']);
}

$roleCheck = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$roleCheck->execute([(int) $_SESSION['user_id']]);

if ($roleCheck->fetchColumn() !== 'admin') {
    respond(403, ['ok' => false, 'message' => 'Administrator access is required.']);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, ['ok' => false, 'message' => 'Invalid request data.']);
}

$action = $input['action'] ?? '';
$allowedTypes = ['electricity', 'water', 'internet', 'other'];

/**
 * Returns true when an account is connected to bills or consumption records.
 * Payments and reminders are linked through bills, so checking bills also
 * protects those related records from being detached from their customer.
 */
function utilityAccountHasRecords(PDO $pdo, int $accountId): bool {
    $stmt = $pdo->prepare(
        'SELECT
            EXISTS(
                SELECT 1 FROM bills WHERE utility_account_id = ?
            ) AS has_bills,
            EXISTS(
                SELECT 1 FROM consumption_records WHERE utility_account_id = ?
            ) AS has_consumption'
    );

    $stmt->execute([$accountId, $accountId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return (bool) ($result['has_bills'] ?? false)
        || (bool) ($result['has_consumption'] ?? false);
}

try {
    if ($action === 'create' || $action === 'update') {
        $userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
        $provider = trim((string) ($input['provider_name'] ?? ''));
        $accountNumber = trim((string) ($input['account_number'] ?? ''));
        $utilityType = (string) ($input['utility_type'] ?? '');

        if (!$userId || $userId < 1 ||
            $provider === '' || mb_strlen($provider) > 150 ||
            $accountNumber === '' || mb_strlen($accountNumber) > 100 ||
            !in_array($utilityType, $allowedTypes, true)) {
            respond(422, [
                'ok' => false,
                'message' => 'Enter a valid customer, provider, account number, and utility type.'
            ]);
        }

        $userCheck = $pdo->prepare(
            'SELECT id FROM users WHERE id = ? LIMIT 1'
        );
        $userCheck->execute([$userId]);

        if (!$userCheck->fetchColumn()) {
            respond(422, [
                'ok' => false,
                'message' => 'The selected customer does not exist.'
            ]);
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO utility_accounts
                 (user_id, provider_name, account_number, utility_type)
                 VALUES (?, ?, ?, ?)'
            );

            $stmt->execute([
                $userId,
                $provider,
                $accountNumber,
                $utilityType
            ]);

            respond(201, [
                'ok' => true,
                'message' => 'Utility account added successfully.'
            ]);
        }

        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            respond(422, [
                'ok' => false,
                'message' => 'A valid utility account ID is required.'
            ]);
        }

        $accountCheck = $pdo->prepare(
            'SELECT id FROM utility_accounts WHERE id = ? LIMIT 1'
        );
        $accountCheck->execute([$id]);

        if (!$accountCheck->fetchColumn()) {
            respond(404, [
                'ok' => false,
                'message' => 'Utility account not found.'
            ]);
        }

        if (utilityAccountHasRecords($pdo, (int) $id)) {
            respond(409, [
                'ok' => false,
                'message' => 'This utility account cannot be edited because it already has linked bills or consumption records. This protects the customer and historical records.'
            ]);
        }

        $stmt = $pdo->prepare(
            'UPDATE utility_accounts
             SET user_id = ?, provider_name = ?, account_number = ?, utility_type = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $userId,
            $provider,
            $accountNumber,
            $utilityType,
            $id
        ]);

        respond(200, [
            'ok' => true,
            'message' => 'Utility account updated successfully.'
        ]);
    }

    if ($action === 'delete') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id || $id < 1) {
            respond(422, [
                'ok' => false,
                'message' => 'A valid utility account ID is required.'
            ]);
        }

        $accountCheck = $pdo->prepare(
            'SELECT id FROM utility_accounts WHERE id = ? LIMIT 1'
        );
        $accountCheck->execute([$id]);

        if (!$accountCheck->fetchColumn()) {
            respond(404, [
                'ok' => false,
                'message' => 'Utility account not found.'
            ]);
        }

        if (utilityAccountHasRecords($pdo, (int) $id)) {
            respond(409, [
                'ok' => false,
                'message' => 'This utility account cannot be deleted because it has linked bills or consumption records. Remove or resolve those records first.'
            ]);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM utility_accounts WHERE id = ?'
        );
        $stmt->execute([$id]);

        respond(200, [
            'ok' => true,
            'message' => 'Utility account deleted successfully.'
        ]);
    }

    respond(400, [
        'ok' => false,
        'message' => 'Unknown utility account action.'
    ]);

} catch (PDOException $e) {
    error_log('Meterly utility account error: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        respond(409, [
            'ok' => false,
            'message' => 'This account cannot be changed or deleted because it is linked to other records, or the account number conflicts with an existing record.'
        ]);
    }

    respond(500, [
        'ok' => false,
        'message' => 'A database error occurred. Check the PHP error log.'
    ]);

} catch (Throwable $e) {
    error_log('Meterly utility account error: ' . $e->getMessage());

    respond(500, [
        'ok' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
