<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $stmt = $pdo->query('SELECT VERSION() AS mysql_version');
    $result = $stmt->fetch();

    echo '<h1>ELMS Database Test</h1>';
    echo '<p>Database connection successful.</p>';
    echo '<p>MySQL Version: ' .
        htmlspecialchars((string)$result['mysql_version']) .
        '</p>';

} catch (Throwable $e) {
    http_response_code(500);

    echo '<h1>Database Connection Failed</h1>';
    echo '<p>Please check the Railway database configuration.</p>';
}