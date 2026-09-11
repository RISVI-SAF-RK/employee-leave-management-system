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
| Leave Type ID
|--------------------------------------------------------------------------
*/

$leaveTypeIdInput =
    $_POST['leave_type_id']
    ?? null;


$leaveTypeId =
    is_string(
        $leaveTypeIdInput
    )
        ? filter_var(
            $leaveTypeIdInput,
            FILTER_VALIDATE_INT
        )
        : false;


if (
    !$leaveTypeId
    ||
    $leaveTypeId < 1
) {

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
    | Begin Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Load And Lock Leave Type
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

             LIMIT 1

             FOR UPDATE"
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
    | Validate Current Status
    |--------------------------------------------------------------------------
    */

    $oldStatus =
        $leaveType[
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
            'Leave type has an invalid status.'
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
    | Commit Status Change
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


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

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


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
