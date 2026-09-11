<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

requireRole('Administrator');

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


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

$csrfToken =
    $_POST['csrf_token']
    ?? null;


if (
    !is_string(
        $csrfToken
    )
    ||
    !verifyCsrfToken(
        $csrfToken
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

$departmentIdInput =
    $_POST['department_id']
    ?? null;


$departmentId =
    is_string(
        $departmentIdInput
    )
        ? filter_var(
            $departmentIdInput,
            FILTER_VALIDATE_INT
        )
        : false;


if (
    !$departmentId
    ||
    $departmentId < 1
) {

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
| Update Department Status
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Begin Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Load And Lock Department
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

             LIMIT 1

             FOR UPDATE"
        );


    $stmt->execute([
        'department_id' =>
            $departmentId
    ]);


    $department =
        $stmt->fetch();


    if (!$department) {

        throw new RuntimeException(
            'Department not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Current Status
    |--------------------------------------------------------------------------
    */

    $oldStatus =
        $department[
            'status'
        ];


    if (
        !in_array(
            $oldStatus,
            [
                'Active',
                'Inactive'
            ],
            true
        )
    ) {

        throw new RuntimeException(
            'Department has an invalid status.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine New Status
    |--------------------------------------------------------------------------
    */

    $newStatus =
        $oldStatus === 'Active'
            ? 'Inactive'
            : 'Active';


    /*
    |--------------------------------------------------------------------------
    | Update Department Status
    |--------------------------------------------------------------------------
    */

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
    | Commit Status Change
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


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

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    error_log(
        'Department status update error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update department status.'
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
