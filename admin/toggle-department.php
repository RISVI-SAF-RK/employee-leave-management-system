<?php

declare(strict_types=1);

require_once __DIR__ .
    '/../includes/role_check.php';

requireRole('Administrator');

require_once __DIR__ .
    '/../includes/functions.php';

require_once __DIR__ .
    '/../config/database.php';


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header(
        'Location: /admin/departments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Validation
|--------------------------------------------------------------------------
*/

if (
    !verifyCsrfToken(
        $_POST['csrf_token']
        ?? null
    )
) {

    http_response_code(403);

    exit(
        'Invalid security token.'
    );
}


/*
|--------------------------------------------------------------------------
| Department ID
|--------------------------------------------------------------------------
*/

$departmentId =
    filter_input(
        INPUT_POST,
        'department_id',
        FILTER_VALIDATE_INT
    );


if (!$departmentId) {

    setFlash(
        'danger',
        'Invalid department.'
    );


    header(
        'Location: /admin/departments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Department
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare(
        "SELECT
            department_id,
            department_name,
            status

         FROM departments

         WHERE department_id =
            :department_id

         LIMIT 1"
    );


$stmt->execute([
    'department_id' =>
        $departmentId
]);


$department =
    $stmt->fetch();


if (!$department) {

    setFlash(
        'danger',
        'Department not found.'
    );


    header(
        'Location: /admin/departments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Determine New Status
|--------------------------------------------------------------------------
*/

$oldStatus =
    $department['status'];


$newStatus =
    $oldStatus === 'Active'
        ? 'Inactive'
        : 'Active';


/*
|--------------------------------------------------------------------------
| Update Department Status
|--------------------------------------------------------------------------
*/

try {

    $update =
        $pdo->prepare(
            "UPDATE departments

             SET status =
                :status

             WHERE department_id =
                :department_id"
        );


    $update->execute([

        'status' =>
            $newStatus,

        'department_id' =>
            $departmentId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audit Department Status Change
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'DEPARTMENT_STATUS_CHANGED',
        'department',
        (int)$departmentId,
        'Changed department '
        . $department[
            'department_name'
        ]
        . ' status from '
        . $oldStatus
        . ' to '
        . $newStatus
        . '.'
    );


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    setFlash(
        'success',
        'Department status updated successfully.'
    );


} catch (Throwable $e) {

    error_log(
        'Department status update error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        'Unable to update department status.'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: /admin/departments.php'
);

exit;