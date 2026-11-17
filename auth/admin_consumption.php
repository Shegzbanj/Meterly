<?php
require_once __DIR__ . '/bootstrap.php';
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') jsonResponse(false, 'Administrator access required.', [], 403);
requirePost();
$data = getJsonInput();
$action = strtolower(trim((string)($data['action'] ?? '')));
try {
    if ($action === 'delete') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) jsonResponse(false, 'Invalid consumption record ID.', [], 422);
        $stmt = $pdo->prepare('DELETE FROM consumption_records WHERE id = ?'); $stmt->execute([$id]);
        if (!$stmt->rowCount()) jsonResponse(false, 'Consumption record not found.', [], 404);
        jsonResponse(true, 'Consumption record deleted successfully.');
    }
    if (!in_array($action, ['create','update'], true)) jsonResponse(false, 'Invalid consumption action.', [], 422);
    $accountId = filter_var($data['utility_account_id'] ?? null, FILTER_VALIDATE_INT);
    $value = $data['consumption_value'] ?? null;
    $unit = trim((string)($data['unit'] ?? ''));
    $date = trim((string)($data['recorded_date'] ?? ''));
    if (!$accountId || $accountId < 1) jsonResponse(false, 'Select a valid utility account.', [], 422);
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0) jsonResponse(false, 'Enter a consumption value greater than zero.', [], 422);
    if ($unit === '' || strlen($unit) > 30) jsonResponse(false, 'Enter a valid unit (maximum 30 characters).', [], 422);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) jsonResponse(false, 'Enter a valid recorded date.', [], 422);
    $check = $pdo->prepare('SELECT id FROM utility_accounts WHERE id = ?'); $check->execute([$accountId]);
    if (!$check->fetchColumn()) jsonResponse(false, 'Selected utility account does not exist.', [], 404);
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO consumption_records (utility_account_id, consumption_value, unit, recorded_date) VALUES (?, ?, ?, ?)');
        $stmt->execute([$accountId, $value, $unit, $date]);
        jsonResponse(true, 'Consumption record created successfully.', ['id' => (int)$pdo->lastInsertId()], 201);
    }
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) jsonResponse(false, 'Invalid consumption record ID.', [], 422);
    $stmt = $pdo->prepare('UPDATE consumption_records SET utility_account_id = ?, consumption_value = ?, unit = ?, recorded_date = ? WHERE id = ?');
    $stmt->execute([$accountId, $value, $unit, $date, $id]);
    if (!$stmt->rowCount()) {
        $exists = $pdo->prepare('SELECT id FROM consumption_records WHERE id = ?'); $exists->execute([$id]);
        if (!$exists->fetchColumn()) jsonResponse(false, 'Consumption record not found.', [], 404);
    }
    jsonResponse(true, 'Consumption record updated successfully.');
} catch (Throwable $e) {
    error_log('Meterly consumption management error: ' . $e->getMessage());
    jsonResponse(false, 'Unable to save the consumption record. Check the database error log.', [], 500);
}
