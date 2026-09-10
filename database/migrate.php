<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Execute SQL statements from a file.
 */
function executeSqlFile(PDO $pdo, string $filePath): void
{
    if (!file_exists($filePath)) {
        throw new RuntimeException(
            'SQL file not found: ' . $filePath
        );
    }

    $sql = file_get_contents($filePath);

    if ($sql === false) {
        throw new RuntimeException(
            'Unable to read SQL file: ' . $filePath
        );
    }

    // Remove full-line SQL comments beginning with --
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    if ($sql === null) {
        throw new RuntimeException('Unable to process SQL file.');
    }

    // Our schema contains normal SQL statements only,
    // so separating by semicolon is sufficient here.
    $statements = array_filter(
        array_map(
            'trim',
            explode(';', $sql)
        )
    );

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

try {

    echo "Starting ELMS database migration..." . PHP_EOL;

    executeSqlFile(
        $pdo,
        __DIR__ . '/schema.sql'
    );

    echo "Schema created successfully." . PHP_EOL;

    executeSqlFile(
        $pdo,
        __DIR__ . '/seed.sql'
    );

    echo "Seed data inserted successfully." . PHP_EOL;

    echo "ELMS database setup completed successfully." . PHP_EOL;

} catch (Throwable $e) {

    echo "Migration failed." . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;

    exit(1);
}