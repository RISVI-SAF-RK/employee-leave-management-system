<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


/*
|--------------------------------------------------------------------------
| Validate Application ID
|--------------------------------------------------------------------------
*/

$applicationId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (
    !$applicationId
    ||
    $applicationId < 1
) {

    http_response_code(400);

    exit(
        'Invalid request.'
    );
}


/*
|--------------------------------------------------------------------------
| Get Current Manager Profile
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
        (int)$_SESSION[
            'user_id'
        ]
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
| Verify Direct-Team Ownership
|--------------------------------------------------------------------------
|
| The Manager may download an attachment only when the employee
| currently reports directly to this Manager.
|
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

            AND e.manager_id =
                :manager_id

         LIMIT 1"
    );


$stmt->execute([

    'application_id' =>
        $applicationId,

    'manager_id' =>
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
    ||
    !is_string(
        $request[
            'attachment'
        ]
    )
) {

    /*
    | Return 404 instead of revealing whether
    | another Manager's employee/application exists.
    */

    http_response_code(404);

    exit(
        'Attachment not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Railway Persistent Volume
|--------------------------------------------------------------------------
*/

$volumePath =
    getenv(
        'RAILWAY_VOLUME_MOUNT_PATH'
    );


if (
    !$volumePath
    ||
    !is_string($volumePath)
) {

    http_response_code(500);

    exit(
        'Attachment storage is unavailable.'
    );
}


/*
|--------------------------------------------------------------------------
| Attachment Storage Directory
|--------------------------------------------------------------------------
*/

$storageDirectory =
    rtrim(
        $volumePath,
        DIRECTORY_SEPARATOR
    )
    . DIRECTORY_SEPARATOR
    . 'leave-attachments';


$realStorageDirectory =
    realpath(
        $storageDirectory
    );


if (
    $realStorageDirectory === false
    ||
    !is_dir(
        $realStorageDirectory
    )
) {

    http_response_code(500);

    exit(
        'Attachment storage is unavailable.'
    );
}


/*
|--------------------------------------------------------------------------
| Safe File Name
|--------------------------------------------------------------------------
*/

$fileName =
    basename(
        $request[
            'attachment'
        ]
    );


if (
    $fileName === ''
    ||
    $fileName === '.'
    ||
    $fileName === '..'
) {

    http_response_code(404);

    exit(
        'Attachment not found.'
    );
}


$filePath =
    $realStorageDirectory
    . DIRECTORY_SEPARATOR
    . $fileName;


$realFilePath =
    realpath(
        $filePath
    );


if (
    $realFilePath === false
    ||
    !is_file(
        $realFilePath
    )
) {

    http_response_code(404);

    exit(
        'Attachment file not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Ensure File Stays Inside Storage Directory
|--------------------------------------------------------------------------
|
| Protect against directory traversal and symbolic-link escape.
|
*/

$allowedPathPrefix =
    $realStorageDirectory
    . DIRECTORY_SEPARATOR;


if (
    !str_starts_with(
        $realFilePath,
        $allowedPathPrefix
    )
) {

    http_response_code(403);

    exit(
        'Invalid attachment path.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate MIME Type
|--------------------------------------------------------------------------
|
| ELMS supports PDF, JPEG and PNG attachments.
|
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$mimeType =
    $finfo->file(
        $realFilePath
    );


$allowedMimeTypes = [

    'application/pdf' =>
        'pdf',

    'image/jpeg' =>
        'jpg',

    'image/png' =>
        'png'
];


if (
    !is_string($mimeType)
    ||
    !isset(
        $allowedMimeTypes[
            $mimeType
        ]
    )
) {

    http_response_code(415);

    exit(
        'Unsupported attachment type.'
    );
}


/*
|--------------------------------------------------------------------------
| Safe Download File Name
|--------------------------------------------------------------------------
*/

$downloadExtension =
    $allowedMimeTypes[
        $mimeType
    ];


$downloadFileName =
    'leave-attachment-'
    . $applicationId
    . '.'
    . $downloadExtension;


/*
|--------------------------------------------------------------------------
| File Size
|--------------------------------------------------------------------------
*/

$fileSize =
    filesize(
        $realFilePath
    );


if ($fileSize === false) {

    http_response_code(500);

    exit(
        'Unable to read attachment.'
    );
}


/*
|--------------------------------------------------------------------------
| Secure Download Response
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: '
    . $mimeType
);


header(
    'Content-Length: '
    . $fileSize
);


header(
    'Content-Disposition: attachment; filename="'
    . $downloadFileName
    . '"'
);


header(
    'X-Content-Type-Options: nosniff'
);


header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);


header(
    'Pragma: no-cache'
);


/*
|--------------------------------------------------------------------------
| Send File
|--------------------------------------------------------------------------
*/

$result =
    readfile(
        $realFilePath
    );


if ($result === false) {

    error_log(
        'Unable to send Manager leave attachment for application #'
        . $applicationId
    );
}


exit;