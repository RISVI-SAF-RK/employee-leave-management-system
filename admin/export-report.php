<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


$currentYear =
    (int)date('Y');


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


$departmentId =
    filter_input(
        INPUT_GET,
        'department_id',
        FILTER_VALIDATE_INT
    );


$sql =
    "SELECT
        la.application_id,

        e.employee_code,

        CONCAT(
            e.first_name,
            ' ',
            e.last_name
        ) AS employee_name,

        d.department_name,

        lt.leave_type_name,

        la.start_date,
        la.end_date,
        la.number_of_days,
        la.status,
        la.applied_at,

        CONCAT(
            manager.first_name,
            ' ',
            manager.last_name
        ) AS manager_name,

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

     LEFT JOIN employees manager
        ON lap.manager_id =
           manager.employee_id

     WHERE YEAR(
        la.start_date
     ) = :report_year";


$params = [
    'report_year' =>
        $selectedYear
];


if ($departmentId) {

    $sql .=
        " AND e.department_id =
            :department_id";

    $params['department_id'] =
        $departmentId;
}


$sql .=
    " ORDER BY
        la.applied_at DESC";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute(
    $params
);


$records =
    $stmt->fetchAll();


$fileName =
    'elms-leave-report-'
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
| UTF-8 BOM for Excel
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
        'Department',
        'Leave Type',
        'Start Date',
        'End Date',
        'Number of Days',
        'Status',
        'Applied At',
        'Manager',
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
                'status'
            ],

            $record[
                'applied_at'
            ],

            $record[
                'manager_name'
            ]
                ?? '',

            $record[
                'decision'
            ]
                ?? '',

            $record[
                'comment'
            ]
                ?? '',

            $record[
                'decision_date'
            ]
                ?? ''
        ]
    );
}


fclose(
    $output
);

exit;