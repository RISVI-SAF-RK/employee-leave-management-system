<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /admin/employees.php'
    );

    exit;
}


if (
    !verifyCsrfToken(
        $_POST['csrf_token'] ?? null
    )
) {

    http_response_code(403);

    exit('Invalid security token.');
}


$employeeId = filter_input(
    INPUT_POST,
    'employee_id',
    FILTER_VALIDATE_INT
);


if (!$employeeId) {

    setFlash(
        'danger',
        'Invalid employee selected.'
    );

    header(
        'Location: /admin/employees.php'
    );

    exit;
}


try {

    $stmt = $pdo->prepare(
        "SELECT
            e.employee_id,
            e.user_id,
            e.status,
            r.role_name
         FROM employees e

         INNER JOIN users u
            ON e.user_id = u.user_id

         INNER JOIN roles r
            ON u.role_id = r.role_id

         WHERE e.employee_id =
            :employee_id

         LIMIT 1"
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
    | Prevent Manager Deactivation When They Still Supervise Active Employees
    |--------------------------------------------------------------------------
    */

    if (
        $employee['role_name']
        === 'Manager'
        &&
        $employee['status']
        === 'Active'
    ) {

        $teamStmt =
            $pdo->prepare(
                "SELECT COUNT(*)
                 FROM employees
                 WHERE manager_id =
                    :manager_id
                   AND status = 'Active'"
            );


        $teamStmt->execute([
            'manager_id' =>
                $employeeId
        ]);


        $activeTeamCount =
            (int)$teamStmt
                ->fetchColumn();


        if ($activeTeamCount > 0) {

            throw new RuntimeException(
                'This Manager cannot be deactivated because active employees are still assigned to them.'
            );
        }
    }


    $newStatus =
        $employee['status']
        === 'Active'
            ? 'Inactive'
            : 'Active';


    $pdo->beginTransaction();


    $employeeUpdate =
        $pdo->prepare(
            "UPDATE employees
             SET status = :status
             WHERE employee_id =
                :employee_id"
        );


    $employeeUpdate->execute([
        'status' =>
            $newStatus,

        'employee_id' =>
            $employeeId
    ]);


    $userUpdate =
        $pdo->prepare(
            "UPDATE users
             SET status = :status
             WHERE user_id =
                :user_id"
        );


    $userUpdate->execute([
        'status' =>
            $newStatus,

        'user_id' =>
            $employee[
                'user_id'
            ]
    ]);


    $pdo->commit();


    setFlash(
        'success',
        'Employee account '
        . strtolower($newStatus)
        . ' successfully.'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
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


header(
    'Location: /admin/employees.php'
);

exit;