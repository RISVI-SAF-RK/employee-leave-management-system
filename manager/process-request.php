<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /manager/pending-requests.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
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
| Input
|--------------------------------------------------------------------------
*/

$applicationId =
    filter_input(
        INPUT_POST,
        'application_id',
        FILTER_VALIDATE_INT
    );


$decisionInput =
    $_POST['decision']
    ?? '';


$decision =
    is_string(
        $decisionInput
    )
        ? $decisionInput
        : '';


$commentInput =
    $_POST['comment']
    ?? '';


$comment =
    is_string(
        $commentInput
    )
        ? trim(
            $commentInput
        )
        : '';


if (!$applicationId) {

    setFlash(
        'danger',
        'Invalid leave request.'
    );

    header(
        'Location: /manager/pending-requests.php'
    );

    exit;
}


if (
    !in_array(
        $decision,
        [
            'Approved',
            'Rejected'
        ],
        true
    )
) {

    setFlash(
        'danger',
        'Invalid leave decision.'
    );

    header(
        'Location: /manager/view-request.php?id='
        . $applicationId
    );

    exit;
}


if (
    $decision === 'Rejected'
    &&
    $comment === ''
) {

    setFlash(
        'danger',
        'Please provide a reason when rejecting a leave request.'
    );

    header(
        'Location: /manager/view-request.php?id='
        . $applicationId
    );

    exit;
}


if (
    strlen($comment) > 2000
) {

    setFlash(
        'danger',
        'Manager comment cannot exceed 2000 characters.'
    );

    header(
        'Location: /manager/view-request.php?id='
        . $applicationId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Manager Profile
|--------------------------------------------------------------------------
*/

$managerStmt =
    $pdo->prepare(
        "SELECT
            employee_id,
            first_name,
            last_name

         FROM employees

         WHERE
            user_id =
                :user_id

            AND status =
                'Active'

         LIMIT 1"
    );


$managerStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$manager =
    $managerStmt->fetch();


if (!$manager) {

    http_response_code(403);

    exit(
        'Active Manager profile not found.'
    );
}


$managerId =
    (int)$manager[
        'employee_id'
    ];


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Application
    |--------------------------------------------------------------------------
    */

    $applicationStmt =
        $pdo->prepare(
            "SELECT
                la.application_id,
                la.employee_id,
                la.leave_type_id,
                la.start_date,
                la.end_date,
                la.number_of_days,
                la.status,

                e.user_id AS employee_user_id,
                e.manager_id,
                e.first_name,
                e.last_name,

                lt.leave_type_name

             FROM leave_applications la

             INNER JOIN employees e
                ON la.employee_id =
                   e.employee_id

             INNER JOIN leave_types lt
                ON la.leave_type_id =
                   lt.leave_type_id

             WHERE
                la.application_id =
                    :application_id

                AND e.manager_id =
                    :manager_id

             LIMIT 1

             FOR UPDATE"
        );


    $applicationStmt->execute([

        'application_id' =>
            $applicationId,

        'manager_id' =>
            $managerId
    ]);


    $application =
        $applicationStmt->fetch();


    if (!$application) {

        throw new RuntimeException(
            'Leave request not found or you are not authorized to process it.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Must Still Be Pending
    |--------------------------------------------------------------------------
    */

    if (
        $application['status']
        !== 'Pending'
    ) {

        throw new RuntimeException(
            'This leave request has already been processed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Make Sure There Is No Existing Approval Record
    |--------------------------------------------------------------------------
    */

    $approvalCheck =
        $pdo->prepare(
            "SELECT approval_id

             FROM leave_approvals

             WHERE application_id =
                :application_id

             LIMIT 1"
        );


    $approvalCheck->execute([
        'application_id' =>
            $applicationId
    ]);


    if (
        $approvalCheck->fetch()
    ) {

        throw new RuntimeException(
            'A decision has already been recorded for this request.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVAL → Lock And Update Leave Balance
    |--------------------------------------------------------------------------
    */

    if ($decision === 'Approved') {

        $leaveYear =
            (int)date(
                'Y',
                strtotime(
                    $application[
                        'start_date'
                    ]
                )
            );


        $balanceStmt =
            $pdo->prepare(
                "SELECT
                    balance_id,
                    allocated_days,
                    used_days,
                    remaining_days

                 FROM leave_balances

                 WHERE
                    employee_id =
                        :employee_id

                    AND leave_type_id =
                        :leave_type_id

                    AND balance_year =
                        :balance_year

                 LIMIT 1

                 FOR UPDATE"
            );


        $balanceStmt->execute([

            'employee_id' =>
                $application[
                    'employee_id'
                ],

            'leave_type_id' =>
                $application[
                    'leave_type_id'
                ],

            'balance_year' =>
                $leaveYear
        ]);


        $balance =
            $balanceStmt->fetch();


        if (!$balance) {

            throw new RuntimeException(
                'The employee does not have a leave balance for this leave type and year.'
            );
        }


        $requestedDays =
            (float)$application[
                'number_of_days'
            ];


        $remainingDays =
            (float)$balance[
                'remaining_days'
            ];


        if (
            $requestedDays >
            $remainingDays
        ) {

            throw new RuntimeException(
                'This request cannot be approved because the employee no longer has enough remaining leave balance.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update Balance
        |--------------------------------------------------------------------------
        */

        $newUsedDays =
            round(
                (float)$balance[
                    'used_days'
                ]
                +
                $requestedDays,
                2
            );


        $newRemainingDays =
            round(
                $remainingDays
                -
                $requestedDays,
                2
            );


        $balanceUpdate =
            $pdo->prepare(
                "UPDATE leave_balances

                 SET
                    used_days =
                        :used_days,

                    remaining_days =
                        :remaining_days

                 WHERE balance_id =
                    :balance_id"
            );


        $balanceUpdate->execute([

            'used_days' =>
                $newUsedDays,

            'remaining_days' =>
                $newRemainingDays,

            'balance_id' =>
                $balance[
                    'balance_id'
                ]
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Create Approval Record
    |--------------------------------------------------------------------------
    */

    $approvalStmt =
        $pdo->prepare(
            "INSERT INTO leave_approvals
            (
                application_id,
                manager_id,
                decision,
                comment
            )

            VALUES
            (
                :application_id,
                :manager_id,
                :decision,
                :comment
            )"
        );


    $approvalStmt->execute([

        'application_id' =>
            $applicationId,

        'manager_id' =>
            $managerId,

        'decision' =>
            $decision,

        'comment' =>
            $comment !== ''
                ? $comment
                : null
    ]);


    /*
    |--------------------------------------------------------------------------
    | Update Application
    |--------------------------------------------------------------------------
    */

    $applicationUpdate =
        $pdo->prepare(
            "UPDATE leave_applications

             SET status =
                :status

             WHERE application_id =
                :application_id"
        );


    $applicationUpdate->execute([

        'status' =>
            $decision,

        'application_id' =>
            $applicationId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Notify Employee
    |--------------------------------------------------------------------------
    */

    $notificationTitle =
        $decision === 'Approved'
            ? 'Leave Application Approved'
            : 'Leave Application Rejected';


    $notificationMessage =
        'Your '
        . $application[
            'leave_type_name'
        ]
        . ' request from '
        . date(
            'd M Y',
            strtotime(
                $application[
                    'start_date'
                ]
            )
        )
        . ' to '
        . date(
            'd M Y',
            strtotime(
                $application[
                    'end_date'
                ]
            )
        )
        . ' has been '
        . strtolower(
            $decision
        )
        . '.';


    if ($comment !== '') {

        $notificationMessage .=
            ' Manager comment: '
            . $comment;
    }


    $notificationStmt =
        $pdo->prepare(
            "INSERT INTO notifications
            (
                user_id,
                title,
                message,
                is_read
            )

            VALUES
            (
                :user_id,
                :title,
                :message,
                FALSE
            )"
        );


    $notificationStmt->execute([

        'user_id' =>
            $application[
                'employee_user_id'
            ],

        'title' =>
            $notificationTitle,

        'message' =>
            $notificationMessage
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
|--------------------------------------------------------------------------
| Audit Manager Decision
|--------------------------------------------------------------------------
*/

logAudit(
    $pdo,
    $decision === 'Approved'
        ? 'LEAVE_APPROVED'
        : 'LEAVE_REJECTED',
    'leave_application',
    (int)$applicationId,
    'Manager '
    . strtolower($decision)
    . ' leave application #'
    . (int)$applicationId
    . '.'
);


    setFlash(
        'success',
        'Leave request '
        . strtolower(
            $decision
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
        'Process leave request error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to process the leave request.'
    );
}


header(
    'Location: /manager/view-request.php?id='
    . $applicationId
);

exit;