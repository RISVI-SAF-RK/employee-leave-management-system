<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$currentYear =
    (int)date('Y');


/*
|--------------------------------------------------------------------------
| Manager
|--------------------------------------------------------------------------
*/

$managerStmt =
    $pdo->prepare(
        "SELECT employee_id

         FROM employees

         WHERE user_id =
            :user_id

         LIMIT 1"
    );


$managerStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$managerId =
    (int)$managerStmt
        ->fetchColumn();


if (!$managerId) {

    http_response_code(403);

    exit(
        'Manager profile not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$selectedYear =
    filter_input(
        INPUT_GET,
        'year',
        FILTER_VALIDATE_INT
    );


if (
    !$selectedYear
    ||
    $selectedYear < 2000
    ||
    $selectedYear > 2100
) {

    $selectedYear =
        $currentYear;
}


$selectedEmployeeId =
    filter_input(
        INPUT_GET,
        'employee_id',
        FILTER_VALIDATE_INT
    );


$selectedLeaveTypeId =
    filter_input(
        INPUT_GET,
        'leave_type_id',
        FILTER_VALIDATE_INT
    );


/*
|--------------------------------------------------------------------------
| Security Validate Employee
|--------------------------------------------------------------------------
*/

if ($selectedEmployeeId) {

    $employeeCheck =
        $pdo->prepare(
            "SELECT employee_id

             FROM employees

             WHERE
                employee_id =
                    :employee_id

                AND manager_id =
                    :manager_id

             LIMIT 1"
        );


    $employeeCheck->execute([

        'employee_id' =>
            $selectedEmployeeId,

        'manager_id' =>
            $managerId
    ]);


    if (
        !$employeeCheck->fetch()
    ) {

        http_response_code(403);

        exit(
            'You are not authorized to export data for this employee.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Export Query
|--------------------------------------------------------------------------
*/

$sql =
    "SELECT
        la.application_id,

        e.employee_code,

        CONCAT(
            e.first_name,
            ' ',
            e.last_name
        ) AS employee_name,

        e.job_title,

        d.department_name,

        lt.leave_type_name,

        la.start_date,
        la.end_date,
        la.number_of_days,
        la.reason,
        la.status,
        la.applied_at,

        lap.decision,
        lap.comment,
        lap.decision_date

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     INNER JOIN departments d
        ON e.department_id =
           d.department_id

     INNER JOIN leave_types lt
        ON la.leave_type_id =
           lt.leave_type_id

     LEFT JOIN leave_approvals lap
        ON la.application_id =
           lap.application_id

     WHERE
        e.manager_id =
            :manager_id

        AND YEAR(
            la.start_date
        ) = :report_year";


$params = [

    'manager_id' =>
        $managerId,

    'report_year' =>
        $selectedYear
];


if ($selectedEmployeeId) {

    $sql .=
        " AND la.employee_id =
            :employee_id";

    $params['employee_id'] =
        $selectedEmployeeId;
}


if ($selectedLeaveTypeId) {

    $sql .=
        " AND la.leave_type_id =
            :leave_type_id";

    $params['leave_type_id'] =
        $selectedLeaveTypeId;
}


$sql .=
    " ORDER BY
        la.applied_at DESC";


$stmt =
    $pdo->prepare($sql);


$stmt->execute(
    $params
);


$records =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CSV
|--------------------------------------------------------------------------
*/

$fileName =
    'elms-team-leave-report-'
    . $selectedYear
    . '.csv';


header(
    'Content-Type: text/csv; charset=UTF-8'
);


header(
    'Content-Disposition: attachment; filename="'
    . $fileName
    . '"'
);


header(
    'X-Content-Type-Options: nosniff'
);


/*
|--------------------------------------------------------------------------
| Excel UTF-8 Support
|--------------------------------------------------------------------------
*/

echo "\xEF\xBB\xBF";


$output =
    fopen(
        'php://output',
        'w'
    );


fputcsv(
    $output,
    [
        'Application ID',
        'Employee Code',
        'Employee Name',
        'Job Title',
        'Department',
        'Leave Type',
        'Start Date',
        'End Date',
        'Number of Days',
        'Reason',
        'Status',
        'Applied At',
        'Decision',
        'Manager Comment',
        'Decision Date'
    ]
);


foreach (
    $records
    as $record
) {

    fputcsv(
        $output,
        [
            $record[
                'application_id'
            ],

            $record[
                'employee_code'
            ],

            $record[
                'employee_name'
            ],

            $record[
                'job_title'
            ],

            $record[
                'department_name'
            ],

            $record[
                'leave_type_name'
            ],

            $record[
                'start_date'
            ],

            $record[
                'end_date'
            ],

            $record[
                'number_of_days'
            ],

            $record[
                'reason'
            ],

            $record[
                'status'
            ],

            $record[
                'applied_at'
            ],

            $record[
                'decision'
            ] ?? '',

            $record[
                'comment'
            ] ?? '',

            $record[
                'decision_date'
            ] ?? ''
        ]
    );
}


fclose(
    $output
);

exit;