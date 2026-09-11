<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


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
| Only Allow Owner To Download
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

         WHERE
            la.application_id =
                :application_id

            AND e.user_id =
                :user_id

         LIMIT 1"
    );


$stmt->execute([

    'application_id' =>
        $applicationId,

    'user_id' =>
        (int)$_SESSION[
            'user_id'
        ]
]);


$application =
    $stmt->fetch();


if (
    !$application
    ||
    empty(
        $application[
            'attachment'
        ]
    )
) {

    http_response_code(404);

    exit(
        'Attachment not found.'
    );
}


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


/*
|--------------------------------------------------------------------------
| basename prevents directory traversal
|--------------------------------------------------------------------------
*/

$fileName =
    basename(
        $application[
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