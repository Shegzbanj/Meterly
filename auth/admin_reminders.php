<?php
require_once __DIR__ . '/bootstrap.php';
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') jsonResponse(false, 'Administrator access required.', [], 403);
requirePost();
$data = getJsonInput();
$action = strtolower(trim((string)($data['action'] ?? '')));
try {
    if ($action === 'delete') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) jsonResponse(false, 'Invalid reminder ID.', [], 422);
        $stmt = $pdo->prepare('DELETE FROM reminders WHERE id = ?'); $stmt->execute([$id]);
        if (!$stmt->rowCount()) jsonResponse(false, 'Reminder not found.', [], 404);
        jsonResponse(true, 'Reminder deleted successfully.');
    }
    if (!in_array($action, ['create','update'], true)) jsonResponse(false, 'Invalid reminder action.', [], 422);
    $billId = filter_var($data['bill_id'] ?? null, FILTER_VALIDATE_INT);
    $date = trim((string)($data['reminder_date'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    $status = strtolower(trim((string)($data['status'] ?? 'pending')));
    if (!$billId || $billId < 1) jsonResponse(false, 'Select a valid bill.', [], 422);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) jsonResponse(false, 'Enter a valid reminder date.', [], 422);
    if ($message === '' || strlen($message) > 255) jsonResponse(false, 'Enter a reminder message (maximum 255 characters).', [], 422);
    if (!in_array($status, ['pending','sent','cancelled'], true)) jsonResponse(false, 'Select a valid reminder status.', [], 422);
    $bill = $pdo->prepare('SELECT id FROM bills WHERE id = ?'); $bill->execute([$billId]);
    if (!$bill->fetchColumn()) jsonResponse(false, 'Selected bill does not exist.', [], 404);
    if ($action === 'create') {
        $stmt = $pdo->prepare('INSERT INTO reminders (bill_id, reminder_date, message, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([$billId, $date, $message, $status]);
        jsonResponse(true, 'Reminder created successfully.', ['id' => (int)$pdo->lastInsertId()], 201);
    }
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) jsonResponse(false, 'Invalid reminder ID.', [], 422);
    $stmt = $pdo->prepare('UPDATE reminders SET bill_id = ?, reminder_date = ?, message = ?, status = ? WHERE id = ?');
    $stmt->execute([$billId, $date, $message, $status, $id]);
    if (!$stmt->rowCount()) {
        $exists = $pdo->prepare('SELECT id FROM reminders WHERE id = ?'); $exists->execute([$id]);
        if (!$exists->fetchColumn()) jsonResponse(false, 'Reminder not found.', [], 404);
    }
    jsonResponse(true, 'Reminder updated successfully.');
} catch (Throwable $e) {
    error_log('Meterly reminder management error: ' . $e->getMessage());
    jsonResponse(false, 'Unable to save the reminder. Check the database error log.', [], 500);
}
