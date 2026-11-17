
<?php

$host = getenv('METERLY_DB_HOST') ?: 'localhost';
$dbname = getenv('METERLY_DB_NAME') ?: 'meterly_db';
$username = getenv('METERLY_DB_USER') ?: 'root';
$password = getenv('METERLY_DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    error_log('Meterly database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact the administrator.');
}
