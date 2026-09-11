<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$applicationId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (!$applicationId) {

    http_response_code(400);

    exit(
        'Invalid request.'
    );
}


/*
|--------------------------------------------------------------------------
| Manager
|--------------------------------------------------------------------------
*/

$managerStmt =
    $pdo->prepare(
        "SELECT employee_id

         FROM employees

         WHERE user_id =
            :user_id

         LIMIT 1"
    );


$managerStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$managerId =
    (int)$managerStmt
        ->fetchColumn();


if (!$managerId) {

    http_response_code(403);

    exit(
        'Manager profile not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Check Manager Access
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare(
        "SELECT
            la.attachment

         FROM leave_applications la

         INNER JOIN employees e
            ON la.employee_id =
               e.employee_id

         LEFT JOIN leave_approvals lap
            ON la.application_id =
               lap.application_id

         WHERE
            la.application_id =
                :application_id

            AND
            (
                e.manager_id =
                    :current_manager_id

                OR lap.manager_id =
                    :approval_manager_id
            )

         LIMIT 1"
    );


$stmt->execute([

    'application_id' =>
        $applicationId,

    'current_manager_id' =>
        $managerId,

    'approval_manager_id' =>
        $managerId
]);


$request =
    $stmt->fetch();


if (
    !$request
    ||
    empty(
        $request[
            'attachment'
        ]
    )
) {

    http_response_code(404);

    exit(
        'Attachment not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Railway Volume
|--------------------------------------------------------------------------
*/

$volumePath =
    getenv(
        'RAILWAY_VOLUME_MOUNT_PATH'
    );


if (!$volumePath) {

    http_response_code(500);

    exit(
        'Attachment storage is unavailable.'
    );
}


$fileName =
    basename(
        $request[
            'attachment'
        ]
    );


$filePath =
    rtrim(
        $volumePath,
        '/'
    )
    . '/leave-attachments/'
    . $fileName;


if (
    !is_file(
        $filePath
    )
) {

    http_response_code(404);

    exit(
        'Attachment file not found.'
    );
}


$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mimeType =
    $finfo->file(
        $filePath
    );


header(
    'Content-Type: '
    . $mimeType
);


header(
    'Content-Length: '
    . filesize(
        $filePath
    )
);


header(
    'Content-Disposition: attachment; filename="leave-attachment-'
    . $applicationId
    . '.'
    . pathinfo(
        $fileName,
        PATHINFO_EXTENSION
    )
    . '"'
);


header(
    'X-Content-Type-Options: nosniff'
);


readfile(
    $filePath
);

exit;