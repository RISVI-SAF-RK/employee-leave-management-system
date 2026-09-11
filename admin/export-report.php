<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


/*
|--------------------------------------------------------------------------
| CSV Formula Injection Protection
|--------------------------------------------------------------------------
|
| Spreadsheet applications may interpret cells beginning with =, +, -, or @
| as formulas. Prefix potentially dangerous values with a single quote so
| they are treated as plain text.
|
*/

function csvSafe(mixed $value): string
{
    $value =
        (string)(
            $value
            ?? ''
        );


    if (
        preg_match(
            '/^[\x00-\x20]*[=+\-@]/u',
            $value
        ) === 1
    ) {

        return "'"
            . $value;
    }


    return $value;
}


/*
|--------------------------------------------------------------------------
| Report Filters
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Load Report Records
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


/*
|--------------------------------------------------------------------------
| CSV Download Headers
|--------------------------------------------------------------------------
*/

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


header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);


header(
    'Pragma: no-cache'
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


if ($output === false) {

    http_response_code(500);

    exit(
        'Unable to generate the report.'
    );
}


/*
|--------------------------------------------------------------------------
| CSV Header Row
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| CSV Data Rows
|--------------------------------------------------------------------------
*/

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

            csvSafe(
                $record[
                    'employee_code'
                ]
            ),

            csvSafe(
                $record[
                    'employee_name'
                ]
            ),

            csvSafe(
                $record[
                    'department_name'
                ]
            ),

            csvSafe(
                $record[
                    'leave_type_name'
                ]
            ),

            $record[
                'start_date'
            ],

            $record[
                'end_date'
            ],

            $record[
                'number_of_days'
            ],

            csvSafe(
                $record[
                    'status'
                ]
            ),

            $record[
                'applied_at'
            ],

            csvSafe(
                $record[
                    'manager_name'
                ]
                ?? ''
            ),

            csvSafe(
                $record[
                    'decision'
                ]
                ?? ''
            ),

            csvSafe(
                $record[
                    'comment'
                ]
                ?? ''
            ),

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
