    $action = $data['action'] ?? '';
    $code = strtoupper(trim((string)($data['code'] ?? '')));

    if (!preg_match('/^[A-Z0-9-]{1,12}$/', $code) || $code === 'OTHER') {
        jsonResponse(false, 'Invalid provider code.', [], 422);
    }

    if ($action === 'add') {
        $label = trim((string)($data['display_name'] ?? ''));
        if ($label === '' || mb_strlen($label) > 150) {
            jsonResponse(false, 'Enter a valid provider name.', [], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO providers (code, display_name, is_active) VALUES (?, ?, 1)');
        $stmt->execute([$code, $label]);
        jsonResponse(true, 'Provider added successfully.', [], 201);
    }

    if ($action === 'update') {
        $label = trim((string)($data['display_name'] ?? ''));
        if ($label === '' || mb_strlen($label) > 150) {
            jsonResponse(false, 'Enter a valid provider name.', [], 422);
        }
        $stmt = $pdo->prepare('UPDATE providers SET display_name = ? WHERE code = ?');
        $stmt->execute([$label, $code]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT 1 FROM providers WHERE code = ?');
            $check->execute([$code]);
            if (!$check->fetchColumn()) jsonResponse(false, 'Provider not found.', [], 404);
        }
        jsonResponse(true, 'Provider name updated.');
    }

    if ($action === 'status') {
        $active = filter_var($data['is_active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($active === null) jsonResponse(false, 'Invalid provider status.', [], 422);
        $stmt = $pdo->prepare('UPDATE providers SET is_active = ? WHERE code = ?');
        $stmt->execute([$active ? 1 : 0, $code]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT 1 FROM providers WHERE code = ?');
            $check->execute([$code]);
            if (!$check->fetchColumn()) jsonResponse(false, 'Provider not found.', [], 404);
        }
        jsonResponse(true, 'Provider status updated.');
    }

    if ($action === 'remove') {
        $stmt = $pdo->prepare('DELETE FROM providers WHERE code = ?');
        $stmt->execute([$code]);
        if ($stmt->rowCount() === 0) jsonResponse(false, 'Provider not found.', [], 404);
        jsonResponse(true, 'Provider removed successfully.');
    }

    jsonResponse(false, 'Unknown provider action.', [], 400);
} catch (PDOException $e) {
    error_log('Meterly provider management error: ' . $e->getMessage());
    if ($e->getCode() === '23000') jsonResponse(false, 'This provider code already exists or is referenced by another record.', [], 409);
    jsonResponse(false, 'Unable to process provider request.', [], 500);
} catch (Throwable $e) {
    error_log('Meterly provider error: ' . $e->getMessage());
    jsonResponse(false, 'Unable to process provider request.', [], 500);
}
