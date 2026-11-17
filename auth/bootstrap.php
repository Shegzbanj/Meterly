
<?php

require_once __DIR__ . '/../config/database.php';

// Return JSON responses.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Configure secure session cookies.
$isHttps = (
    !empty($_SERVER['HTTPS']) &&
    strtolower((string) $_SERVER['HTTPS']) !== 'off'
);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // Derive the cookie path from this installation so localhost subfolders work.
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/meterly/auth/endpoint.php'));
    $projectPath = rtrim(dirname(dirname($scriptPath)), '/');
    if ($projectPath === '.' || $projectPath === '/') {
        $projectPath = '';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ($projectPath === '' ? '/' : $projectPath . '/'),
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Send a standard JSON response.
function jsonResponse(
    bool $ok,
    string $message,
    array $extra = [],
    int $status = 200
): void {
    http_response_code($status);

    echo json_encode(
        array_merge([
            'ok' => $ok,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Read JSON request data.
function getJsonInput(): array {
    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        jsonResponse(false, 'Invalid request data.', [], 400);
    }

    return $input;
}

// Allow only POST requests.
function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed.', [], 405);
    }
}
