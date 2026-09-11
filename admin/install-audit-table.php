<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


try {

    $sql =
        file_get_contents(
            __DIR__
            . '/../database/audit_logs.sql'
        );


    if ($sql === false) {

        throw new RuntimeException(
            'Unable to read audit migration file.'
        );
    }


    $pdo->exec($sql);


    echo '
        <h2>Audit table created successfully.</h2>

        <p>
            You can now remove
            admin/install-audit-table.php.
        </p>
    ';


} catch (Throwable $e) {

    http_response_code(500);

    echo '<h2>Migration failed.</h2>';

    echo '<pre>'
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
        . '</pre>';
}