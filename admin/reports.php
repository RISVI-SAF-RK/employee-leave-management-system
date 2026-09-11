<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');

$pageTitle = 'Reports & Analytics';

$currentYear = (int)date('Y');


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$selectedYear = filter_input(
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
    $selectedYear = $currentYear;
}


$departmentId = filter_input(
    INPUT_GET,
    'department_id',
    FILTER_VALIDATE_INT
);


if (!$departmentId) {
    $departmentId = null;
}


/*
|--------------------------------------------------------------------------
| Departments
|--------------------------------------------------------------------------
*/

$departmentStmt = $pdo->query(
    "SELECT
        department_id,
        department_name

     FROM departments

     ORDER BY department_name"
);


$departments =
    $departmentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Shared Conditions
|--------------------------------------------------------------------------
*/

$where = [
    "YEAR(la.start_date) = :report_year"
];


$params = [
    'report_year' =>
        $selectedYear
];


if ($departmentId) {

    $where[] =
        "e.department_id =
            :department_id";

    $params['department_id'] =
        $departmentId;
}


$whereSql =
    implode(
        ' AND ',
        $where
    );


/*
|--------------------------------------------------------------------------
| Main Summary
|--------------------------------------------------------------------------
*/

$summarySql =
    "SELECT
        COUNT(*) AS total_applications,

        SUM(
            CASE
                WHEN la.status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_applications,

        SUM(
            CASE
                WHEN la.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_applications,

        SUM(
            CASE
                WHEN la.status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejected_applications,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status = 'Approved'
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
| Leave Type Analysis
|--------------------------------------------------------------------------
*/

$typeSql =
    "SELECT
        lt.leave_type_name,

        COUNT(
            la.application_id
        ) AS request_count,

        SUM(
            CASE
                WHEN la.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status = 'Approved'
                    THEN la.number_of_days
                    ELSE 0
                END
            ),
            0
        ) AS approved_days

     FROM leave_types lt

     LEFT JOIN leave_applications la
        ON lt.leave_type_id =
           la.leave_type_id

        AND YEAR(
            la.start_date
        ) = :type_year

     LEFT JOIN employees e
        ON la.employee_id =
           e.employee_id";


$typeParams = [
    'type_year' =>
        $selectedYear
];


if ($departmentId) {

    $typeSql .=
        " AND e.department_id =
            :type_department_id";

    $typeParams[
        'type_department_id'
    ] =
        $departmentId;
}


$typeSql .=
    " GROUP BY
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
| Department Analysis
|--------------------------------------------------------------------------
*/

$departmentSql =
    "SELECT
        d.department_name,

        COUNT(
            la.application_id
        ) AS request_count,

        SUM(
            CASE
                WHEN la.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_count,

        SUM(
            CASE
                WHEN la.status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejected_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status = 'Approved'
                    THEN la.number_of_days
                    ELSE 0
                END
            ),
            0
        ) AS approved_days

     FROM departments d

     LEFT JOIN employees e
        ON d.department_id =
           e.department_id

     LEFT JOIN leave_applications la
        ON e.employee_id =
           la.employee_id

        AND YEAR(
            la.start_date
        ) = :department_year

     GROUP BY
        d.department_id,
        d.department_name

     ORDER BY
        approved_days DESC,
        d.department_name ASC";


$departmentReportStmt =
    $pdo->prepare(
        $departmentSql
    );


$departmentReportStmt->execute([
    'department_year' =>
        $selectedYear
]);


$departmentStats =
    $departmentReportStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Monthly Application Trend
|--------------------------------------------------------------------------
*/

$monthlySql =
    "SELECT
        MONTH(
            la.start_date
        ) AS report_month,

        COUNT(*) AS application_count,

        COALESCE(
            SUM(
                CASE
                    WHEN la.status = 'Approved'
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
    . $whereSql
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
    $params
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
            $monthlyLookup[$month]
        )
            ? (int)$monthlyLookup[
                $month
            ][
                'application_count'
            ]
            : 0;


    $approvedDays =
        isset(
            $monthlyLookup[$month]
        )
            ? (float)$monthlyLookup[
                $month
            ][
                'approved_days'
            ]
            : 0.00;


    $monthlyStats[] = [
        'month' =>
            $monthNames[$month],

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


require_once __DIR__
    . '/../includes/admin/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/admin/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Reports
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
                Reports & Analytics
            </h2>

            <p>
                Monitor organizational leave
                activity and management
                information.
            </p>

        </div>


        <a
            href="/admin/export-report.php?year=<?= $selectedYear ?><?= $departmentId
                ? '&department_id=' . $departmentId
                : ''
            ?>"
            class="btn-professional-secondary"
        >

            <i
                class="bi
                       bi-download"
            ></i>

            Export CSV

        </a>

    </div>

</div>


<!-- =========================================================
     FILTER
     ========================================================= -->

<div class="admin-card mb-4">

    <div class="admin-card-body">

        <form
            method="GET"
            class="row
                   g-3
                   align-items-end"
        >

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


            <div class="col-md-5">

                <label
                    for="department_id"
                    class="professional-form-label"
                >
                    Department
                </label>


                <select
                    id="department_id"
                    name="department_id"
                    class="form-select
                           professional-input"
                >

                    <option value="">
                        All Departments
                    </option>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>

                        <option
                            value="<?= (int)$department[
                                'department_id'
                            ] ?>"
                            <?= (
                                $departmentId
                                ===
                                (int)$department[
                                    'department_id'
                                ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= escape(
                                $department[
                                    'department_name'
                                ]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-md-3">

                <button
                    type="submit"
                    class="btn-professional-primary
                           w-100"
                >

                    <i class="bi bi-bar-chart"></i>

                    Generate Report

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card">

            <span>
                Applications
            </span>

            <strong>
                <?= (int)(
                    $summary[
                        'total_applications'
                    ] ?? 0
                ) ?>
            </strong>

            <small>
                Total requests
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card pending">

            <span>
                Pending
            </span>

            <strong>
                <?= (int)(
                    $summary[
                        'pending_applications'
                    ] ?? 0
                ) ?>
            </strong>

            <small>
                Awaiting decisions
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card approved">

            <span>
                Approved
            </span>

            <strong>
                <?= (int)(
                    $summary[
                        'approved_applications'
                    ] ?? 0
                ) ?>
            </strong>

            <small>
                Approved requests
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card rejected">

            <span>
                Rejected
            </span>

            <strong>
                <?= (int)(
                    $summary[
                        'rejected_applications'
                    ] ?? 0
                ) ?>
            </strong>

            <small>
                Rejected requests
            </small>

        </div>

    </div>


    <div class="col-sm-6 col-xl">

        <div class="report-kpi-card days">

            <span>
                Approved Days
            </span>

            <strong>
                <?= number_format(
                    (float)(
                        $summary[
                            'approved_leave_days'
                        ] ?? 0
                    ),
                    2
                ) ?>
            </strong>

            <small>
                Leave days used
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
                TREND
            </div>

            <h5>
                Monthly Leave Applications
            </h5>

            <small class="text-muted">
                Application volume during
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


                <div class="report-chart-column">

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
                            class="report-chart-bar"
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
                            ] ?> application(s)"
                        ></div>

                    </div>


                    <span>
                        <?= escape(
                            $month['month']
                        ) ?>
                    </span>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>


<div class="row g-4">

    <!-- =====================================================
         LEAVE TYPES
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
                                        <?= (int)$type[
                                            'approved_count'
                                        ] ?>
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
         DEPARTMENTS
         ===================================================== -->

    <div class="col-xl-6">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        ORGANIZATION
                    </div>

                    <h5>
                        Department Summary
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
                                <th>Department</th>
                                <th>Requests</th>
                                <th>Approved</th>
                                <th>Days</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $departmentStats
                                as $department
                            ): ?>

                                <tr>

                                    <td>

                                        <strong
                                            class="admin-record-type"
                                        >

                                            <?= escape(
                                                $department[
                                                    'department_name'
                                                ]
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?= (int)$department[
                                            'request_count'
                                        ] ?>

                                    </td>


                                    <td>

                                        <?= (int)$department[
                                            'approved_count'
                                        ] ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= number_format(
                                                (float)$department[
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

</div>


<?php

require_once __DIR__
    . '/../includes/admin/footer.php';