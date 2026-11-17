
<?php

require_once __DIR__ . '/bootstrap.php';

requirePost();

$data = getJsonInput();

$name = trim($data['name'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

// Validate name.
if ($name === '' || strlen($name) > 150) {
    jsonResponse(false, 'Please enter a valid full name.', [], 422);
}

// Validate email.
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}

// Validate password.
if (
    strlen($password) < 12 ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)
) {
    jsonResponse(
        false,
        'Password must contain at least 12 characters, an uppercase letter, a lowercase letter, a number and a symbol.',
        [],
        422
    );
}

try {
    // Check whether the email already exists.
    $check = $pdo->prepare(
        'SELECT id FROM users WHERE email = ? LIMIT 1'
    );

    $check->execute([$email]);

    if ($check->fetch()) {
        jsonResponse(
            false,
            'An account with this email address already exists.',
            [],
            409
        );
    }

    // Securely hash the password.
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Create the customer account.
    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role)
         VALUES (?, ?, ?, ?)'
    );

    $insert->execute([
        $name,
        $email,
        $passwordHash,
        'customer'
    ]);

    $userId = (int) $pdo->lastInsertId();

    // Start a new authenticated session.
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'customer';

    jsonResponse(
        true,
        'Your Meterly account has been created successfully.',
        [
            'user' => [
                'id' => $userId,
                'email' => $email,
                'full_name' => $name,
                'role' => 'customer'
            ]
        ],
        201
    );

} catch (PDOException $e) {
    error_log('Meterly registration error: ' . $e->getMessage());

    jsonResponse(
        false,
        'Registration could not be completed. Please try again.',
        [],
        500
    );
}
