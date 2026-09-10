<?php

declare(strict_types=1);

$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if (
    !$host ||
    !$dbName ||
    !$username ||
    $password === false
) {
    throw new RuntimeException(
        'Database environment variables are not configured.'
    );
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());

    throw new RuntimeException(
        'Unable to connect to the database.'
    );
}