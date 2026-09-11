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
        'Location: /admin/employees.php'
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
| Employee ID
|--------------------------------------------------------------------------
*/

$employeeIdInput =
    $_POST['employee_id']
    ?? null;


$employeeId =
    is_string(
        $employeeIdInput
    )
        ? filter_var(
            $employeeIdInput,
            FILTER_VALIDATE_INT
        )
        : false;


if (
    !$employeeId
    ||
    $employeeId < 1
) {

    setFlash(
        'danger',
        'Invalid employee selected.'
    );

    header(
        'Location: /admin/employees.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Update Employee Status
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
    | Load And Lock Employee
    |--------------------------------------------------------------------------
    |
    | Locking the employee row ensures the status and role used for this
    | decision cannot change underneath this request.
    |
    */

    $stmt =
        $pdo->prepare(
            "SELECT
                e.employee_id,
                e.user_id,
                e.employee_code,
                e.first_name,
                e.last_name,
                e.status,

                r.role_name

             FROM employees e

             INNER JOIN users u
                ON e.user_id =
                   u.user_id

             INNER JOIN roles r
                ON u.role_id =
                   r.role_id

             WHERE e.employee_id =
                :employee_id

             LIMIT 1

             FOR UPDATE"
        );


    $stmt->execute([
        'employee_id' =>
            $employeeId
    ]);


    $employee =
        $stmt->fetch();


    if (!$employee) {

        throw new RuntimeException(
            'Employee not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine New Status
    |--------------------------------------------------------------------------
    */

    $oldStatus =
        $employee['status'];


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
            'Employee account has an invalid status.'
        );
    }


    $newStatus =
        $oldStatus === 'Active'
            ? 'Inactive'
            : 'Active';


    /*
    |--------------------------------------------------------------------------
    | Prevent Manager Deactivation With Active Team Members
    |--------------------------------------------------------------------------
    */

    if (
        $employee['role_name']
        === 'Manager'
        &&
        $newStatus
        === 'Inactive'
    ) {

        $teamStmt =
            $pdo->prepare(
                "SELECT
                    employee_id

                 FROM employees

                 WHERE manager_id =
                    :manager_id

                   AND status =
                    'Active'

                 FOR UPDATE"
            );


        $teamStmt->execute([
            'manager_id' =>
                $employeeId
        ]);


        if (
            $teamStmt->fetch()
        ) {

            throw new RuntimeException(
                'This Manager cannot be deactivated because active employees are still assigned to them.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Employee Record
    |--------------------------------------------------------------------------
    */

    $employeeUpdate =
        $pdo->prepare(
            "UPDATE employees

             SET status =
                :status

             WHERE employee_id =
                :employee_id"
        );


    $employeeUpdate->execute([

        'status' =>
            $newStatus,

        'employee_id' =>
            $employeeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Update User Account
    |--------------------------------------------------------------------------
    */

    $userUpdate =
        $pdo->prepare(
            "UPDATE users

             SET status =
                :status

             WHERE user_id =
                :user_id"
        );


    $userUpdate->execute([

        'status' =>
            $newStatus,

        'user_id' =>
            (int)$employee[
                'user_id'
            ]
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit Status Change
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Audit Employee Status Change
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'EMPLOYEE_STATUS_CHANGED',
        'employee',
        (int)$employeeId,
        'Changed '
        . $employee['role_name']
        . ' '
        . $employee['employee_code']
        . ' - '
        . $employee['first_name']
        . ' '
        . $employee['last_name']
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
        'Employee account '
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
        'Employee status error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update employee status.'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: /admin/employees.php'
);

exit;
