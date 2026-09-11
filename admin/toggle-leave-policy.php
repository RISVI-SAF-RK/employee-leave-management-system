<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


/*
|--------------------------------------------------------------------------
| Only POST Requests
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header(
        'Location: /admin/leave-policies.php'
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
| Policy ID
|--------------------------------------------------------------------------
*/

$policyIdInput =
    $_POST['policy_id']
    ?? null;


$policyId =
    is_string(
        $policyIdInput
    )
        ? filter_var(
            $policyIdInput,
            FILTER_VALIDATE_INT
        )
        : false;


if (
    !$policyId
    ||
    $policyId < 1
) {

    setFlash(
        'danger',
        'Invalid leave policy selected.'
    );


    header(
        'Location: /admin/leave-policies.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Change Policy Status
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
    | Load And Lock Policy + Leave Type
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare(
            "SELECT
                lp.policy_id,
                lp.leave_type_id,
                lp.status,

                lt.leave_type_name

             FROM leave_policies lp

             INNER JOIN leave_types lt
                ON lp.leave_type_id =
                   lt.leave_type_id

             WHERE lp.policy_id =
                :policy_id

             LIMIT 1

             FOR UPDATE"
        );


    $stmt->execute([
        'policy_id' =>
            $policyId
    ]);


    $policy =
        $stmt->fetch();


    if (!$policy) {

        throw new RuntimeException(
            'Leave policy not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Current Status
    |--------------------------------------------------------------------------
    */

    $oldStatus =
        $policy[
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
            'Leave policy has an invalid status.'
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
    | Update Policy Status
    |--------------------------------------------------------------------------
    */

    $updateStmt =
        $pdo->prepare(
            "UPDATE leave_policies

             SET status =
                :status

             WHERE policy_id =
                :policy_id"
        );


    $updateStmt->execute([

        'status' =>
            $newStatus,

        'policy_id' =>
            $policyId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit Status Change
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Audit Policy Status Change
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'LEAVE_POLICY_STATUS_CHANGED',
        'leave_policy',
        (int)$policyId,
        'Changed policy for '
        . $policy[
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
        'Leave policy '
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
        'Toggle leave policy error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update the leave policy.'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: /admin/leave-policies.php'
);

exit;
