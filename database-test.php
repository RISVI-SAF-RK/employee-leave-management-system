<?php

declare(strict_types=1);

try {

    require_once __DIR__ . '/config/database.php';

    $stmt = $pdo->query(
        'SELECT VERSION() AS mysql_version'
    );

    $result = $stmt->fetch();

    echo '<h1>ELMS Database Test</h1>';

    echo '<p>Database connection successful.</p>';

    echo '<p>MySQL Version: ' .
        htmlspecialchars(
            (string)$result['mysql_version'],
            ENT_QUOTES,
            'UTF-8'
        ) .
        '</p>';

} catch (Throwable $e) {

    error_log(
        'ELMS DATABASE TEST ERROR: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo '<h1>ELMS Database Test</h1>';

    echo '<p>Database connection failed.</p>';

    echo '<p>Please check the Railway deployment logs.</p>';
}