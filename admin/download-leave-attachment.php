<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


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


if (
    !$attachment
    ||
    !is_string($attachment)
) {

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
| Safe Storage Directory
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
|
| basename() prevents directory traversal values such as ../../file.pdf
| from being used directly as a path.
|
*/

$fileName =
    basename(
        $attachment
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
| Ensure File Is Inside Attachment Directory
|--------------------------------------------------------------------------
|
| This also protects against an unexpected symbolic link pointing outside
| the Railway leave-attachments directory.
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
    'leave-document-'
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
| Secure File Response
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
| Send Attachment
|--------------------------------------------------------------------------
*/

$result =
    readfile(
        $realFilePath
    );


if ($result === false) {

    error_log(
        'Unable to send leave attachment for application #'
        . $applicationId
    );
}


exit;