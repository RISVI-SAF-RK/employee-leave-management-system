<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


$pageTitle = 'Leave Records';


/*
|--------------------------------------------------------------------------
| Filters
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


$status =
    $_GET['status']
    ?? 'All';


$allowedStatuses = [
    'All',
    'Pending',
    'Approved',
    'Rejected'
];


if (
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $status = 'All';
}


$departmentId =
    filter_input(
        INPUT_GET,
        'department_id',
        FILTER_VALIDATE_INT
    );


$leaveTypeId =
    filter_input(
        INPUT_GET,
        'leave_type_id',
        FILTER_VALIDATE_INT
    );


$search =
    trim(
        $_GET['search']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| Filter Options
|--------------------------------------------------------------------------
*/

$departmentStmt =
    $pdo->query(
        "SELECT
            department_id,
            department_name

         FROM departments

         ORDER BY department_name"
    );


$departments =
    $departmentStmt->fetchAll();


$leaveTypeStmt =
    $pdo->query(
        "SELECT
            leave_type_id,
            leave_type_name

         FROM leave_types

         ORDER BY leave_type_name"
    );


$leaveTypes =
    $leaveTypeStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Shared Filter Conditions
|--------------------------------------------------------------------------
*/

$where = [
    "YEAR(la.start_date) = :record_year"
];


$params = [
    'record_year' =>
        $selectedYear
];


if ($departmentId) {

    $where[] =
        "e.department_id =
            :department_id";

    $params['department_id'] =
        $departmentId;
}


if ($leaveTypeId) {

    $where[] =
        "la.leave_type_id =
            :leave_type_id";

    $params['leave_type_id'] =
        $leaveTypeId;
}


if ($search !== '') {

    $where[] =
        "CONCAT_WS(
            ' ',
            e.employee_code,
            e.first_name,
            e.last_name,
            u.email
        ) LIKE :search";

    $params['search'] =
        '%' . $search . '%';
}


$whereSql =
    implode(
        ' AND ',
        $where
    );


/*
|--------------------------------------------------------------------------
| Summary Counters
|--------------------------------------------------------------------------
*/

$countSql =
    "SELECT
        COUNT(*) AS total_records,

        SUM(
            CASE
                WHEN la.status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pending_records,

        SUM(
            CASE
                WHEN la.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approved_records,

        SUM(
            CASE
                WHEN la.status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejected_records

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     INNER JOIN users u
        ON e.user_id =
           u.user_id

     WHERE "
     . $whereSql;


$countStmt =
    $pdo->prepare(
        $countSql
    );


$countStmt->execute(
    $params
);


$counts =
    $countStmt->fetch();


/*
|--------------------------------------------------------------------------
| Records
|--------------------------------------------------------------------------
*/

$recordWhere =
    $where;


$recordParams =
    $params;


if ($status !== 'All') {

    $recordWhere[] =
        "la.status =
            :status";

    $recordParams['status'] =
        $status;
}


$recordWhereSql =
    implode(
        ' AND ',
        $recordWhere
    );


$sql =
    "SELECT
        la.application_id,
        la.start_date,
        la.end_date,
        la.number_of_days,
        la.reason,
        la.attachment,
        la.status,
        la.applied_at,
        la.updated_at,

        e.employee_code,
        e.first_name,
        e.last_name,
        e.job_title,

        u.email,

        d.department_name,

        lt.leave_type_name,
        lt.is_paid,

        lap.decision,
        lap.comment,
        lap.decision_date,

        CONCAT(
            manager.first_name,
            ' ',
            manager.last_name
        ) AS decision_manager

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     INNER JOIN users u
        ON e.user_id =
           u.user_id

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

     WHERE "
     . $recordWhereSql
     . "

     ORDER BY
        la.applied_at DESC

     LIMIT 200";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute(
    $recordParams
);


$records =
    $stmt->fetchAll();


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
            Leave Records
        </span>

    </div>


    <div
        class="d-flex
               flex-column
               flex-md-row
               justify-content-between
               align-items-md-center
               gap-3"
    >

        <div>

            <h2>
                Leave Records
            </h2>

            <p>
                Review centralized employee
                leave applications and
                approval information.
            </p>

        </div>


        <div
            class="admin-record-year-badge"
        >

            <i
                class="bi
                       bi-calendar3"
            ></i>

            <?= $selectedYear ?>

        </div>

    </div>

</div>


<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Total Records
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'total_records'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       purple"
            >

                <i
                    class="bi
                           bi-journal-text"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Pending
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'pending_records'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       orange"
            >

                <i
                    class="bi
                           bi-hourglass-split"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Approved
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'approved_records'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       green"
            >

                <i
                    class="bi
                           bi-check-circle"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Rejected
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'rejected_records'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>


            <div
                class="leave-summary-red"
            >

                <i
                    class="bi
                           bi-x-circle"
                ></i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     FILTERS
     ========================================================= -->

<div
    class="admin-card
           mb-4"
>

    <div class="admin-card-header">

        <div>

            <h5>
                Search & Filter
            </h5>

            <small class="text-muted">
                Narrow leave records by
                year, status, employee,
                department or leave type.
            </small>

        </div>


        <div class="header-icon-box">

            <i
                class="bi
                       bi-funnel"
            ></i>

        </div>

    </div>


    <div class="admin-card-body">

        <form
            method="GET"
            class="row
                   g-3
                   align-items-end"
        >

            <!-- Search -->

            <div class="col-lg-4">

                <label
                    for="search"
                    class="professional-form-label"
                >
                    Employee
                </label>


                <div
                    class="admin-search-input"
                >

                    <i
                        class="bi
                               bi-search"
                    ></i>


                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control
                               professional-input"
                        placeholder="Name, employee code or email"
                        value="<?= escape(
                            $search
                        ) ?>"
                    >

                </div>

            </div>


            <!-- Year -->

            <div class="col-sm-6 col-lg-2">

                <label
                    for="year"
                    class="professional-form-label"
                >
                    Year
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


            <!-- Status -->

            <div class="col-sm-6 col-lg-2">

                <label
                    for="status"
                    class="professional-form-label"
                >
                    Status
                </label>


                <select
                    id="status"
                    name="status"
                    class="form-select
                           professional-input"
                >

                    <?php foreach (
                        $allowedStatuses
                        as $allowedStatus
                    ): ?>

                        <option
                            value="<?= escape(
                                $allowedStatus
                            ) ?>"
                            <?= $status
                                === $allowedStatus
                                    ? 'selected'
                                    : ''
                            ?>
                        >

                            <?= escape(
                                $allowedStatus
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Department -->

            <div class="col-md-6 col-lg-2">

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


            <!-- Leave Type -->

            <div class="col-md-6 col-lg-2">

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
                        All Types
                    </option>


                    <?php foreach (
                        $leaveTypes
                        as $leaveType
                    ): ?>

                        <option
                            value="<?= (int)$leaveType[
                                'leave_type_id'
                            ] ?>"
                            <?= (
                                $leaveTypeId
                                ===
                                (int)$leaveType[
                                    'leave_type_id'
                                ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= escape(
                                $leaveType[
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

                    <i class="bi bi-funnel-fill"></i>

                    Apply Filters

                </button>


                <a
                    href="/admin/leave-records.php"
                    class="btn-professional-secondary"
                >

                    <i
                        class="bi
                               bi-arrow-counterclockwise"
                    ></i>

                    Reset

                </a>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     RECORDS TABLE
     ========================================================= -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                Employee Leave Applications
            </h5>

            <small class="text-muted">

                <?= count(
                    $records
                ) ?>
                record(s) shown

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
                            Leave Type
                        </th>

                        <th>
                            Leave Period
                        </th>

                        <th>
                            Duration
                        </th>

                        <th>
                            Manager
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Applied
                        </th>

                        <th class="text-end">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$records): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="text-center py-5"
                        >

                            <div
                                class="employee-empty-state"
                            >

                                <div
                                    class="employee-empty-icon"
                                >

                                    <i
                                        class="bi
                                               bi-journal-x"
                                    ></i>

                                </div>


                                <h6>
                                    No leave records found
                                </h6>


                                <p>
                                    Try changing the selected
                                    filters or search value.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $records
                    as $record
                ): ?>

                    <tr>

                        <!-- Employee -->

                        <td>

                            <div class="employee-cell">

                                <div class="employee-avatar">

                                    <?= escape(
                                        strtoupper(
                                            substr(
                                                $record[
                                                    'first_name'
                                                ],
                                                0,
                                                1
                                            )
                                            .
                                            substr(
                                                $record[
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
                                            $record[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $record[
                                                'last_name'
                                            ]
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= escape(
                                            $record[
                                                'employee_code'
                                            ]
                                        ) ?>

                                    </span>


                                    <small>

                                        <?= escape(
                                            $record[
                                                'department_name'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>


                        <!-- Leave Type -->

                        <td>

                            <strong
                                class="admin-record-type"
                            >

                                <?= escape(
                                    $record[
                                        'leave_type_name'
                                    ]
                                ) ?>

                            </strong>


                            <small
                                class="d-block
                                       text-muted"
                            >

                                <?= (int)$record[
                                    'is_paid'
                                ] === 1
                                    ? 'Paid Leave'
                                    : 'Unpaid Leave'
                                ?>

                            </small>

                        </td>


                        <!-- Dates -->

                        <td>

                            <span
                                class="admin-record-date"
                            >

                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $record[
                                                'start_date'
                                            ]
                                        )
                                    )
                                ) ?>

                            </span>


                            <small
                                class="d-block
                                       text-muted"
                            >

                                to

                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $record[
                                                'end_date'
                                            ]
                                        )
                                    )
                                ) ?>

                            </small>

                        </td>


                        <!-- Days -->

                        <td>

                            <strong>

                                <?= number_format(
                                    (float)$record[
                                        'number_of_days'
                                    ],
                                    2
                                ) ?>

                            </strong>

                            <small class="text-muted">
                                days
                            </small>

                        </td>


                        <!-- Manager -->

                        <td>

                            <?php if (
                                !empty(
                                    $record[
                                        'decision_manager'
                                    ]
                                )
                            ): ?>

                                <span
                                    class="admin-manager-name"
                                >

                                    <i
                                        class="bi
                                               bi-person-check"
                                    ></i>

                                    <?= escape(
                                        $record[
                                            'decision_manager'
                                        ]
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    class="text-muted small"
                                >
                                    Awaiting decision
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Status -->

                        <td>

                            <span
                                class="leave-status-badge
                                       leave-status-<?= strtolower(
                                           $record[
                                               'status'
                                           ]
                                       ) ?>"
                            >

                                <?= escape(
                                    $record[
                                        'status'
                                    ]
                                ) ?>

                            </span>

                        </td>


                        <!-- Applied -->

                        <td>

                            <span
                                class="admin-record-date"
                            >

                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $record[
                                                'applied_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            </span>


                            <small
                                class="d-block
                                       text-muted"
                            >

                                <?= escape(
                                    date(
                                        'H:i',
                                        strtotime(
                                            $record[
                                                'applied_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            </small>

                        </td>


                        <!-- Action -->

                        <td class="text-end">

                            <a
                                href="/admin/view-leave-record.php?id=<?= (int)$record['application_id'] ?>"
                                class="btn
                                       btn-sm
                                       btn-outline-primary"
                            >

                                <i class="bi bi-eye"></i>

                                View

                            </a>

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
    . '/../includes/admin/footer.php';