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
| Only POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /admin/leave-policies.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
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
| Policy ID
|--------------------------------------------------------------------------
*/

$policyId = filter_input(
    INPUT_POST,
    'policy_id',
    FILTER_VALIDATE_INT
);


if (!$policyId) {

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
| Change Status
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $pdo->prepare(
            "SELECT
                policy_id,
                status

             FROM leave_policies

             WHERE policy_id =
                :policy_id

             LIMIT 1"
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


    $newStatus =
        $policy['status']
        === 'Active'
            ? 'Inactive'
            : 'Active';


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


    setFlash(
        'success',
        'Leave policy '
        . strtolower($newStatus)
        . ' successfully.'
    );


} catch (Throwable $e) {

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


header(
    'Location: /admin/leave-policies.php'
);

exit;