<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$pageTitle =
    'Team Reports';

$currentYear =
    (int)date('Y');


/*
|--------------------------------------------------------------------------
| Current Manager
|--------------------------------------------------------------------------
*/

$managerStmt =
    $pdo->prepare(
        "SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,

            d.department_name

         FROM employees e

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         WHERE e.user_id =
            :user_id

         LIMIT 1"
    );


$managerStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$managerProfile =
    $managerStmt->fetch();


if (!$managerProfile) {

    http_response_code(403);

    exit(
        'Manager profile not found.'
    );
}


$managerId =
    (int)$managerProfile[
        'employee_id'
    ];


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


if (!$selectedEmployeeId) {

    $selectedEmployeeId =
        null;
}


$selectedLeaveTypeId =
    filter_input(
        INPUT_GET,
        'leave_type_id',
        FILTER_VALIDATE_INT
    );


if (!$selectedLeaveTypeId) {

    $selectedLeaveTypeId =
        null;
}


/*
|--------------------------------------------------------------------------
| Load Direct Reports
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Every employee here must have manager_id = logged-in Manager ID.
|--------------------------------------------------------------------------
*/

$teamStmt =
    $pdo->prepare(
        "SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,
            e.status,

            d.department_name

         FROM employees e

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         WHERE e.manager_id =
            :manager_id

         ORDER BY
            e.first_name,
            e.last_name"
    );


$teamStmt->execute([
    'manager_id' =>
        $managerId
]);


$teamMembers =
    $teamStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Security Check For Employee Filter
|--------------------------------------------------------------------------
|
| A Manager must never be able to manually type:
|
| ?employee_id=999
|
| and access someone outside their team.
|--------------------------------------------------------------------------
*/

if ($selectedEmployeeId) {

    $validTeamEmployee =
        false;


    foreach (
        $teamMembers
        as $teamMember
    ) {

        if (
            (int)$teamMember[
                'employee_id'
            ]
            ===
            $selectedEmployeeId
        ) {

            $validTeamEmployee =
                true;

            break;
        }
    }


    if (!$validTeamEmployee) {

        $selectedEmployeeId =
            null;
    }
}


/*
|--------------------------------------------------------------------------
| Leave Types
|--------------------------------------------------------------------------
*/

$leaveTypeStmt =
    $pdo->query(
        "SELECT
            leave_type_id,
            leave_type_name

         FROM leave_types

         ORDER BY
            leave_type_name"
    );


$leaveTypes =
    $leaveTypeStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Shared Report Conditions
|--------------------------------------------------------------------------
*/

$where = [
    "e.manager_id =
        :manager_id",

    "YEAR(la.start_date) =
        :report_year"
];


$params = [

    'manager_id' =>
        $managerId,

    'report_year' =>
        $selectedYear
];


if ($selectedEmployeeId) {

    $where[] =
        "la.employee_id =
            :employee_id";

    $params['employee_id'] =
        $selectedEmployeeId;
}


if ($selectedLeaveTypeId) {

    $where[] =
        "la.leave_type_id =
            :leave_type_id";

    $params['leave_type_id'] =
        $selectedLeaveTypeId;
}


$whereSql =
    implode(
        ' AND ',
        $where
    );


/*
|--------------------------------------------------------------------------
| Report Summary
|--------------------------------------------------------------------------
*/

$summarySql =
    "SELECT
        COUNT(*) AS total_applications,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Pending'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_applications,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS approved_applications,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Rejected'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS rejected_applications,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN la.number_of_days
                    ELSE 0
                END
            ),
            0
        ) AS approved_leave_days

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     WHERE "
    . $whereSql;


$summaryStmt =
    $pdo->prepare(
        $summarySql
    );


$summaryStmt->execute(
    $params
);


$summary =
    $summaryStmt->fetch();


/*
|--------------------------------------------------------------------------
| Leave Type Usage
|--------------------------------------------------------------------------
*/

$typeWhere = [
    "e.manager_id =
        :type_manager_id",

    "YEAR(la.start_date) =
        :type_year"
];


$typeParams = [

    'type_manager_id' =>
        $managerId,

    'type_year' =>
        $selectedYear
];


if ($selectedEmployeeId) {

    $typeWhere[] =
        "la.employee_id =
            :type_employee_id";

    $typeParams[
        'type_employee_id'
    ] =
        $selectedEmployeeId;
}


if ($selectedLeaveTypeId) {

    $typeWhere[] =
        "la.leave_type_id =
            :selected_type_id";

    $typeParams[
        'selected_type_id'
    ] =
        $selectedLeaveTypeId;
}


$typeSql =
    "SELECT
        lt.leave_type_name,

        COUNT(
            la.application_id
        ) AS request_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Pending'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS approved_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Rejected'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS rejected_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN la.number_of_days
                    ELSE 0
                END
            ),
            0
        ) AS approved_days

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     INNER JOIN leave_types lt
        ON la.leave_type_id =
           lt.leave_type_id

     WHERE "
    . implode(
        ' AND ',
        $typeWhere
    )
    . "

     GROUP BY
        lt.leave_type_id,
        lt.leave_type_name

     ORDER BY
        approved_days DESC,
        lt.leave_type_name ASC";


$typeStmt =
    $pdo->prepare(
        $typeSql
    );


$typeStmt->execute(
    $typeParams
);


$leaveTypeStats =
    $typeStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Monthly Trend
|--------------------------------------------------------------------------
*/

$monthlyWhere = [
    "e.manager_id =
        :monthly_manager_id",

    "YEAR(la.start_date) =
        :monthly_year"
];


$monthlyParams = [

    'monthly_manager_id' =>
        $managerId,

    'monthly_year' =>
        $selectedYear
];


if ($selectedEmployeeId) {

    $monthlyWhere[] =
        "la.employee_id =
            :monthly_employee_id";

    $monthlyParams[
        'monthly_employee_id'
    ] =
        $selectedEmployeeId;
}


if ($selectedLeaveTypeId) {

    $monthlyWhere[] =
        "la.leave_type_id =
            :monthly_leave_type_id";

    $monthlyParams[
        'monthly_leave_type_id'
    ] =
        $selectedLeaveTypeId;
}


$monthlySql =
    "SELECT
        MONTH(
            la.start_date
        ) AS report_month,

        COUNT(*) AS application_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN la.number_of_days
                    ELSE 0
                END
            ),
            0
        ) AS approved_days

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     WHERE "
    . implode(
        ' AND ',
        $monthlyWhere
    )
    . "

     GROUP BY
        MONTH(
            la.start_date
        )

     ORDER BY
        report_month";


$monthlyStmt =
    $pdo->prepare(
        $monthlySql
    );


$monthlyStmt->execute(
    $monthlyParams
);


$monthlyRaw =
    $monthlyStmt->fetchAll();


$monthlyLookup = [];


foreach (
    $monthlyRaw
    as $month
) {

    $monthlyLookup[
        (int)$month[
            'report_month'
        ]
    ] = $month;
}


$monthNames = [

    1 => 'Jan',
    2 => 'Feb',
    3 => 'Mar',
    4 => 'Apr',
    5 => 'May',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Aug',
    9 => 'Sep',
    10 => 'Oct',
    11 => 'Nov',
    12 => 'Dec'
];


$monthlyStats = [];

$maxMonthlyApplications = 0;


for (
    $month = 1;
    $month <= 12;
    $month++
) {

    $applicationCount =
        isset(
            $monthlyLookup[
                $month
            ]
        )
            ? (int)$monthlyLookup[
                $month
            ][
                'application_count'
            ]
            : 0;


    $approvedDays =
        isset(
            $monthlyLookup[
                $month
            ]
        )
            ? (float)$monthlyLookup[
                $month
            ][
                'approved_days'
            ]
            : 0.00;


    $monthlyStats[] = [

        'month' =>
            $monthNames[
                $month
            ],

        'applications' =>
            $applicationCount,

        'approved_days' =>
            $approvedDays
    ];


    $maxMonthlyApplications =
        max(
            $maxMonthlyApplications,
            $applicationCount
        );
}


/*
|--------------------------------------------------------------------------
| Team Employee Summary
|--------------------------------------------------------------------------
*/

$employeeJoinConditions = [
    "YEAR(la.start_date) =
        :employee_report_year"
];


$employeeParams = [

    'employee_manager_id' =>
        $managerId,

    'employee_report_year' =>
        $selectedYear
];


if ($selectedLeaveTypeId) {

    $employeeJoinConditions[] =
        "la.leave_type_id =
            :employee_leave_type_id";

    $employeeParams[
        'employee_leave_type_id'
    ] =
        $selectedLeaveTypeId;
}


$employeeSql =
    "SELECT
        e.employee_id,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.job_title,
        e.status,

        d.department_name,

        COUNT(
            la.application_id
        ) AS total_requests,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Pending'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS pending_requests,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS approved_requests,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Rejected'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS rejected_requests,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status =
                        'Approved'
                    THEN la.number_of_days
                    ELSE 0
                END
            ),
            0
        ) AS approved_days

     FROM employees e

     INNER JOIN departments d
        ON e.department_id =
           d.department_id

     LEFT JOIN leave_applications la
        ON la.employee_id =
           e.employee_id

        AND "
        . implode(
            ' AND ',
            $employeeJoinConditions
        )
        . "

     WHERE e.manager_id =
        :employee_manager_id";


if ($selectedEmployeeId) {

    $employeeSql .=
        " AND e.employee_id =
            :summary_employee_id";

    $employeeParams[
        'summary_employee_id'
    ] =
        $selectedEmployeeId;
}


$employeeSql .=
    " GROUP BY
        e.employee_id,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.job_title,
        e.status,
        d.department_name

      ORDER BY
        approved_days DESC,
        e.first_name ASC,
        e.last_name ASC";


$employeeStmt =
    $pdo->prepare(
        $employeeSql
    );


$employeeStmt->execute(
    $employeeParams
);


$employeeStats =
    $employeeStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Active Team Count
|--------------------------------------------------------------------------
*/

$activeTeamCount = 0;


foreach (
    $teamMembers
    as $member
) {

    if (
        $member['status']
        === 'Active'
    ) {

        $activeTeamCount++;
    }
}


require_once __DIR__
    . '/../includes/manager/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/manager/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Team Reports
        </span>

    </div>


    <div
        class="d-flex
               flex-column
               flex-lg-row
               justify-content-between
               align-items-lg-center
               gap-3"
    >

        <div>

            <h2>
                Team Reports & Analytics
            </h2>

            <p>
                Monitor leave activity for
                employees currently assigned
                to your team.
            </p>

        </div>


        <a
            href="/manager/export-team-report.php?year=<?= $selectedYear ?><?= $selectedEmployeeId
                ? '&employee_id='
                    . $selectedEmployeeId
                : ''
            ?><?= $selectedLeaveTypeId
                ? '&leave_type_id='
                    . $selectedLeaveTypeId
                : ''
            ?>"
            class="btn-professional-secondary"
        >

            <i class="bi bi-download"></i>

            Export CSV

        </a>

    </div>

</div>


<!-- =========================================================
     REPORT FILTERS
     ========================================================= -->

<div class="admin-card mb-4">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                REPORT FILTERS
            </div>

            <h5>
                Generate Team Report
            </h5>

            <small class="text-muted">
                Analyze the whole team or
                narrow the report to one
                employee or leave type.
            </small>

        </div>


        <div class="header-icon-box">

            <i class="bi bi-funnel"></i>

        </div>

    </div>


    <div class="admin-card-body">

        <form
            method="GET"
            class="row
                   g-3
                   align-items-end"
        >

            <!-- YEAR -->

            <div class="col-md-4">

                <label
                    for="year"
                    class="professional-form-label"
                >
                    Reporting Year
                </label>


                <select
                    id="year"
                    name="year"
                    class="form-select
                           professional-input"
                >

                    <?php for (
                        $year =
                            $currentYear + 1;

                        $year >=
                            $currentYear - 4;

                        $year--
                    ): ?>

                        <option
                            value="<?= $year ?>"
                            <?= $year
                                === $selectedYear
                                    ? 'selected'
                                    : ''
                            ?>
                        >
                            <?= $year ?>
                        </option>

                    <?php endfor; ?>

                </select>

            </div>


            <!-- EMPLOYEE -->

            <div class="col-md-4">

                <label
                    for="employee_id"
                    class="professional-form-label"
                >
                    Team Member
                </label>


                <select
                    id="employee_id"
                    name="employee_id"
                    class="form-select
                           professional-input"
                >

                    <option value="">
                        All Team Members
                    </option>


                    <?php foreach (
                        $teamMembers
                        as $member
                    ): ?>

                        <option
                            value="<?= (int)$member[
                                'employee_id'
                            ] ?>"
                            <?= (
                                $selectedEmployeeId
                                ===
                                (int)$member[
                                    'employee_id'
                                ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= escape(
                                $member[
                                    'employee_code'
                                ]
                                . ' — '
                                . $member[
                                    'first_name'
                                ]
                                . ' '
                                . $member[
                                    'last_name'
                                ]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- LEAVE TYPE -->

            <div class="col-md-4">

                <label
                    for="leave_type_id"
                    class="professional-form-label"
                >
                    Leave Type
                </label>


                <select
                    id="leave_type_id"
                    name="leave_type_id"
                    class="form-select
                           professional-input"
                >

                    <option value="">
                        All Leave Types
                    </option>


                    <?php foreach (
                        $leaveTypes
                        as $type
                    ): ?>

                        <option
                            value="<?= (int)$type[
                                'leave_type_id'
                            ] ?>"
                            <?= (
                                $selectedLeaveTypeId
                                ===
                                (int)$type[
                                    'leave_type_id'
                                ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= escape(
                                $type[
                                    'leave_type_name'
                                ]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div
                class="col-12
                       d-flex
                       flex-wrap
                       gap-2"
            >

                <button
                    type="submit"
                    class="btn-professional-primary"
                >

                    <i
                        class="bi
                               bi-bar-chart-line"
                    ></i>

                    Generate Report

                </button>


                <a
                    href="/manager/team-reports.php"
                    class="btn-professional-secondary"
                >

                    <i
                        class="bi
                               bi-arrow-counterclockwise"
                    ></i>

                    Reset Filters

                </a>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     SUMMARY KPIs
     ========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card">

            <span>
                Active Team
            </span>

            <strong>
                <?= $activeTeamCount ?>
            </strong>

            <small>
                Assigned employees
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card">

            <span>
                Applications
            </span>

            <strong>

                <?= (int)(
                    $summary[
                        'total_applications'
                    ]
                    ?? 0
                ) ?>

            </strong>

            <small>
                Total requests
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div
            class="report-kpi-card
                   pending"
        >

            <span>
                Pending
            </span>

            <strong>

                <?= (int)(
                    $summary[
                        'pending_applications'
                    ]
                    ?? 0
                ) ?>

            </strong>

            <small>
                Require review
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div
            class="report-kpi-card
                   approved"
        >

            <span>
                Approved
            </span>

            <strong>

                <?= (int)(
                    $summary[
                        'approved_applications'
                    ]
                    ?? 0
                ) ?>

            </strong>

            <small>
                Approved requests
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div
            class="report-kpi-card
                   days"
        >

            <span>
                Approved Days
            </span>

            <strong>

                <?= number_format(
                    (float)(
                        $summary[
                            'approved_leave_days'
                        ]
                        ?? 0
                    ),
                    2
                ) ?>

            </strong>

            <small>
                Team leave usage
            </small>

        </div>

    </div>

</div>


<!-- =========================================================
     MONTHLY TREND
     ========================================================= -->

<div class="admin-card mb-4">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                TEAM TREND
            </div>

            <h5>
                Monthly Leave Applications
            </h5>

            <small class="text-muted">
                Team request volume during
                <?= $selectedYear ?>
            </small>

        </div>


        <div class="header-icon-box">

            <i
                class="bi
                       bi-graph-up-arrow"
            ></i>

        </div>

    </div>


    <div class="admin-card-body">

        <div class="report-chart">

            <?php foreach (
                $monthlyStats
                as $month
            ): ?>

                <?php

                $barHeight =
                    $maxMonthlyApplications > 0
                        ? (
                            $month[
                                'applications'
                            ]
                            /
                            $maxMonthlyApplications
                        ) * 100
                        : 0;

                ?>


                <div
                    class="report-chart-column"
                >

                    <div
                        class="report-chart-value"
                    >

                        <?= $month[
                            'applications'
                        ] ?>

                    </div>


                    <div
                        class="report-chart-track"
                    >

                        <div
                            class="report-chart-bar
                                   manager-report-bar"
                            style="height:
                            <?= number_format(
                                $barHeight,
                                2,
                                '.',
                                ''
                            ) ?>%;"
                            title="<?= escape(
                                $month['month']
                            ) ?>: <?= $month[
                                'applications'
                            ] ?> request(s)"
                        ></div>

                    </div>


                    <span>

                        <?= escape(
                            $month[
                                'month'
                            ]
                        ) ?>

                    </span>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>


<div class="row g-4 mb-4">

    <!-- =====================================================
         LEAVE TYPE SUMMARY
         ===================================================== -->

    <div class="col-xl-6">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        LEAVE USAGE
                    </div>

                    <h5>
                        Usage by Leave Type
                    </h5>

                </div>

            </div>


            <div class="admin-card-body p-0">

                <div class="table-responsive">

                    <table
                        class="table
                               table-hover
                               align-middle"
                    >

                        <thead>

                            <tr>
                                <th>Leave Type</th>
                                <th>Requests</th>
                                <th>Approved</th>
                                <th>Days</th>
                            </tr>

                        </thead>


                        <tbody>

                        <?php if (
                            !$leaveTypeStats
                        ): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center
                                           py-4
                                           text-muted"
                                >
                                    No leave activity
                                    for this selection.
                                </td>

                            </tr>

                        <?php endif; ?>


                        <?php foreach (
                            $leaveTypeStats
                            as $type
                        ): ?>

                            <tr>

                                <td>

                                    <strong
                                        class="admin-record-type"
                                    >

                                        <?= escape(
                                            $type[
                                                'leave_type_name'
                                            ]
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= (int)$type[
                                        'request_count'
                                    ] ?>

                                </td>


                                <td>

                                    <span
                                        class="team-report-approved"
                                    >

                                        <?= (int)$type[
                                            'approved_count'
                                        ] ?>

                                    </span>

                                </td>


                                <td>

                                    <strong>

                                        <?= number_format(
                                            (float)$type[
                                                'approved_days'
                                            ],
                                            2
                                        ) ?>

                                    </strong>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         DECISION SUMMARY
         ===================================================== -->

    <div class="col-xl-6">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        DECISIONS
                    </div>

                    <h5>
                        Approval Overview
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <?php

                $totalApplications =
                    (int)(
                        $summary[
                            'total_applications'
                        ]
                        ?? 0
                    );


                $approvedApplications =
                    (int)(
                        $summary[
                            'approved_applications'
                        ]
                        ?? 0
                    );


                $pendingApplications =
                    (int)(
                        $summary[
                            'pending_applications'
                        ]
                        ?? 0
                    );


                $rejectedApplications =
                    (int)(
                        $summary[
                            'rejected_applications'
                        ]
                        ?? 0
                    );


                $approvedPercent =
                    $totalApplications > 0
                        ? (
                            $approvedApplications
                            /
                            $totalApplications
                        ) * 100
                        : 0;


                $pendingPercent =
                    $totalApplications > 0
                        ? (
                            $pendingApplications
                            /
                            $totalApplications
                        ) * 100
                        : 0;


                $rejectedPercent =
                    $totalApplications > 0
                        ? (
                            $rejectedApplications
                            /
                            $totalApplications
                        ) * 100
                        : 0;

                ?>


                <div class="team-report-status-list">

                    <div>

                        <div
                            class="team-report-status-heading"
                        >

                            <span>
                                Approved
                            </span>

                            <strong>
                                <?= number_format(
                                    $approvedPercent,
                                    1
                                ) ?>%
                            </strong>

                        </div>


                        <div
                            class="team-report-progress"
                        >

                            <div
                                class="approved"
                                style="width:
                                <?= number_format(
                                    $approvedPercent,
                                    2,
                                    '.',
                                    ''
                                ) ?>%;"
                            ></div>

                        </div>

                    </div>


                    <div>

                        <div
                            class="team-report-status-heading"
                        >

                            <span>
                                Pending
                            </span>

                            <strong>
                                <?= number_format(
                                    $pendingPercent,
                                    1
                                ) ?>%
                            </strong>

                        </div>


                        <div
                            class="team-report-progress"
                        >

                            <div
                                class="pending"
                                style="width:
                                <?= number_format(
                                    $pendingPercent,
                                    2,
                                    '.',
                                    ''
                                ) ?>%;"
                            ></div>

                        </div>

                    </div>


                    <div>

                        <div
                            class="team-report-status-heading"
                        >

                            <span>
                                Rejected
                            </span>

                            <strong>
                                <?= number_format(
                                    $rejectedPercent,
                                    1
                                ) ?>%
                            </strong>

                        </div>


                        <div
                            class="team-report-progress"
                        >

                            <div
                                class="rejected"
                                style="width:
                                <?= number_format(
                                    $rejectedPercent,
                                    2,
                                    '.',
                                    ''
                                ) ?>%;"
                            ></div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     TEAM MEMBER SUMMARY
     ========================================================= -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                TEAM SUMMARY
            </div>

            <h5>
                Employee Leave Summary
            </h5>

            <small class="text-muted">
                Leave activity for employees
                who report directly to you.
            </small>

        </div>

    </div>


    <div class="admin-card-body p-0">

        <div class="table-responsive">

            <table
                class="table
                       table-hover
                       align-middle"
            >

                <thead>

                    <tr>

                        <th>
                            Employee
                        </th>

                        <th>
                            Requests
                        </th>

                        <th>
                            Pending
                        </th>

                        <th>
                            Approved
                        </th>

                        <th>
                            Rejected
                        </th>

                        <th>
                            Approved Days
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (
                    !$employeeStats
                ): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center py-5"
                        >

                            <div class="employee-empty-state">

                                <div class="employee-empty-icon">

                                    <i class="bi bi-people"></i>

                                </div>

                                <h6>
                                    No team members found
                                </h6>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $employeeStats
                    as $employee
                ): ?>

                    <tr>

                        <td>

                            <div class="employee-cell">

                                <div class="employee-avatar">

                                    <?= escape(
                                        strtoupper(
                                            substr(
                                                $employee[
                                                    'first_name'
                                                ],
                                                0,
                                                1
                                            )
                                            .
                                            substr(
                                                $employee[
                                                    'last_name'
                                                ],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>

                                </div>


                                <div class="employee-info">

                                    <strong>

                                        <?= escape(
                                            $employee[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $employee[
                                                'last_name'
                                            ]
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= escape(
                                            $employee[
                                                'employee_code'
                                            ]
                                        ) ?>

                                    </span>


                                    <small>

                                        <?= escape(
                                            $employee[
                                                'job_title'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>


                        <td>

                            <strong>

                                <?= (int)$employee[
                                    'total_requests'
                                ] ?>

                            </strong>

                        </td>


                        <td>

                            <span
                                class="leave-status-badge
                                       leave-status-pending"
                            >

                                <?= (int)$employee[
                                    'pending_requests'
                                ] ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="leave-status-badge
                                       leave-status-approved"
                            >

                                <?= (int)$employee[
                                    'approved_requests'
                                ] ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="leave-status-badge
                                       leave-status-rejected"
                            >

                                <?= (int)$employee[
                                    'rejected_requests'
                                ] ?>

                            </span>

                        </td>


                        <td>

                            <strong
                                class="team-report-days"
                            >

                                <?= number_format(
                                    (float)$employee[
                                        'approved_days'
                                    ],
                                    2
                                ) ?>

                            </strong>

                            <small class="text-muted">
                                days
                            </small>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/manager/footer.php';