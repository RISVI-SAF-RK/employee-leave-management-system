<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


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
| Get Attachment
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare(
        "SELECT attachment

         FROM leave_applications

         WHERE application_id =
            :application_id

         LIMIT 1"
    );


$stmt->execute([
    'application_id' =>
        $applicationId
]);


$attachment =
    $stmt->fetchColumn();


if (!$attachment) {

    http_response_code(404);

    exit(
        'Attachment not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Persistent Railway Volume
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
        (string)$attachment
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


/*
|--------------------------------------------------------------------------
| Safe File Response
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mimeType =
    $finfo->file(
        $filePath
    );


$extension =
    pathinfo(
        $fileName,
        PATHINFO_EXTENSION
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
    'Content-Disposition: attachment; filename="leave-document-'
    . $applicationId
    . '.'
    . $extension
    . '"'
);


header(
    'X-Content-Type-Options: nosniff'
);


readfile(
    $filePath
);

exit;