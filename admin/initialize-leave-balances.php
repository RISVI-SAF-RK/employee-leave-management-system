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
| POST Only
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header(
        'Location: /admin/leave-balances.php'
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
| Year
|--------------------------------------------------------------------------
*/

$balanceYearInput =
    $_POST['balance_year']
    ?? null;


$balanceYear =
    is_string(
        $balanceYearInput
    )
        ? filter_var(
            $balanceYearInput,
            FILTER_VALIDATE_INT
        )
        : false;


if (
    !$balanceYear
    ||
    $balanceYear < 2000
    ||
    $balanceYear > 2100
) {

    setFlash(
        'danger',
        'Invalid leave balance year.'
    );

    header(
        'Location: /admin/leave-balances.php'
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | Begin Transaction
    |--------------------------------------------------------------------------
    |
    | Balance initialization is handled as one unit so that partial batches
    | are not left behind if any insert fails.
    |
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Load Active Employees
    |--------------------------------------------------------------------------
    */

    $employeeStmt =
        $pdo->query(
            "SELECT
                e.employee_id

             FROM employees e

             INNER JOIN users u
                ON e.user_id =
                   u.user_id

             INNER JOIN roles r
                ON u.role_id =
                   r.role_id

             WHERE
                e.status =
                    'Active'

                AND u.status =
                    'Active'

                AND r.role_name IN (
                    'Employee',
                    'Manager'
                )

             ORDER BY
                e.employee_id"
        );


    $employees =
        $employeeStmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Load Active Leave Policies
    |--------------------------------------------------------------------------
    */

    $policyStmt =
        $pdo->query(
            "SELECT
                lp.leave_type_id,
                lp.days_per_year,
                lp.carry_forward_allowed,
                lp.max_carry_forward_days

             FROM leave_policies lp

             INNER JOIN leave_types lt
                ON lp.leave_type_id =
                   lt.leave_type_id

             WHERE
                lp.status =
                    'Active'

                AND lt.status =
                    'Active'

             ORDER BY
                lp.leave_type_id"
        );


    $policies =
        $policyStmt->fetchAll();


    if (!$employees) {

        throw new RuntimeException(
            'No active employees are available.'
        );
    }


    if (!$policies) {

        throw new RuntimeException(
            'No active leave policies are configured.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prepared Statements
    |--------------------------------------------------------------------------
    */

    $existingStmt =
        $pdo->prepare(
            "SELECT
                balance_id

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


    $previousStmt =
        $pdo->prepare(
            "SELECT
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


    $insertStmt =
        $pdo->prepare(
            "INSERT INTO leave_balances
            (
                employee_id,
                leave_type_id,
                balance_year,
                allocated_days,
                used_days,
                remaining_days
            )

            VALUES
            (
                :employee_id,
                :leave_type_id,
                :balance_year,
                :allocated_days,
                0.00,
                :remaining_days
            )"
        );


    /*
    |--------------------------------------------------------------------------
    | Generate Missing Balances
    |--------------------------------------------------------------------------
    */

    $createdCount = 0;
    $skippedCount = 0;

    $previousYear =
        $balanceYear - 1;


    foreach (
        $employees
        as $employee
    ) {

        foreach (
            $policies
            as $policy
        ) {

            $employeeId =
                (int)$employee[
                    'employee_id'
                ];


            $leaveTypeId =
                (int)$policy[
                    'leave_type_id'
                ];


            /*
            |--------------------------------------------------------------------------
            | Don't Overwrite An Existing Balance
            |--------------------------------------------------------------------------
            */

            $existingStmt->execute([

                'employee_id' =>
                    $employeeId,

                'leave_type_id' =>
                    $leaveTypeId,

                'balance_year' =>
                    $balanceYear
            ]);


            if (
                $existingStmt->fetch()
            ) {

                $skippedCount++;

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Base Annual Entitlement
            |--------------------------------------------------------------------------
            */

            $baseEntitlement =
                max(
                    0,
                    (float)$policy[
                        'days_per_year'
                    ]
                );


            /*
            |--------------------------------------------------------------------------
            | Carry Forward
            |--------------------------------------------------------------------------
            */

            $carryForward =
                0.00;


            if (
                (int)$policy[
                    'carry_forward_allowed'
                ] === 1
            ) {

                $previousStmt->execute([

                    'employee_id' =>
                        $employeeId,

                    'leave_type_id' =>
                        $leaveTypeId,

                    'balance_year' =>
                        $previousYear
                ]);


                $previousBalance =
                    $previousStmt->fetch();


                if ($previousBalance) {

                    $previousRemaining =
                        max(
                            0,
                            (float)$previousBalance[
                                'remaining_days'
                            ]
                        );


                    $maximumCarry =
                        max(
                            0,
                            (float)$policy[
                                'max_carry_forward_days'
                            ]
                        );


                    $carryForward =
                        min(
                            $previousRemaining,
                            $maximumCarry
                        );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Final Allocation
            |--------------------------------------------------------------------------
            */

            $allocatedDays =
                round(
                    $baseEntitlement
                    +
                    $carryForward,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Create Balance
            |--------------------------------------------------------------------------
            */

            $insertStmt->execute([

                'employee_id' =>
                    $employeeId,

                'leave_type_id' =>
                    $leaveTypeId,

                'balance_year' =>
                    $balanceYear,

                'allocated_days' =>
                    $allocatedDays,

                'remaining_days' =>
                    $allocatedDays
            ]);


            $createdCount++;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Audit Initialization
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'LEAVE_BALANCES_INITIALIZED',
        'leave_balance',
        null,
        'Initialized leave balances for '
        . $balanceYear
        . ': '
        . $createdCount
        . ' created, '
        . $skippedCount
        . ' existing balance(s) skipped.'
    );


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    setFlash(
        'success',
        $createdCount
        . ' leave balance(s) created. '
        . $skippedCount
        . ' existing balance(s) were left unchanged.'
    );


} catch (Throwable $e) {

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    error_log(
        'Initialize balances error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to initialize leave balances.'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: /admin/leave-balances.php?year='
    . urlencode(
        (string)$balanceYear
    )
);

exit;
