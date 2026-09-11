<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';


requireRole('Administrator');


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
        'Location: /admin/leave-types.php'
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
| Leave Type ID
|--------------------------------------------------------------------------
*/

$leaveTypeId =
    filter_input(
        INPUT_POST,
        'leave_type_id',
        FILTER_VALIDATE_INT
    );


if (!$leaveTypeId) {

    setFlash(
        'danger',
        'Invalid leave type selected.'
    );


    header(
        'Location: /admin/leave-types.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Update Leave Type Status
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Load Leave Type
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare(
            "SELECT
                leave_type_id,
                leave_type_name,
                status

             FROM leave_types

             WHERE leave_type_id =
                :leave_type_id

             LIMIT 1"
        );


    $stmt->execute([
        'leave_type_id' =>
            $leaveTypeId
    ]);


    $leaveType =
        $stmt->fetch();


    if (!$leaveType) {

        throw new RuntimeException(
            'Leave type not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine New Status
    |--------------------------------------------------------------------------
    */

    $oldStatus =
        $leaveType['status'];


    $newStatus =
        $oldStatus === 'Active'
            ? 'Inactive'
            : 'Active';


    /*
    |--------------------------------------------------------------------------
    | Update Status
    |--------------------------------------------------------------------------
    */

    $updateStmt =
        $pdo->prepare(
            "UPDATE leave_types

             SET status =
                :status

             WHERE leave_type_id =
                :leave_type_id"
        );


    $updateStmt->execute([

        'status' =>
            $newStatus,

        'leave_type_id' =>
            $leaveTypeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audit Leave Type Status Change
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'LEAVE_TYPE_STATUS_CHANGED',
        'leave_type',
        (int)$leaveTypeId,
        'Changed leave type '
        . $leaveType[
            'leave_type_name'
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
        'Leave type '
        . strtolower(
            $newStatus
        )
        . ' successfully.'
    );


} catch (Throwable $e) {

    error_log(
        'Toggle leave type error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update leave type status.'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: /admin/leave-types.php'
);

exit;