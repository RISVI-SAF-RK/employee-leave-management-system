<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Leave Balances';


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$currentYear = (int)date('Y');

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


$selectedEmployeeId = filter_input(
    INPUT_GET,
    'employee_id',
    FILTER_VALIDATE_INT
);

if (!$selectedEmployeeId) {
    $selectedEmployeeId = null;
}


/*
|--------------------------------------------------------------------------
| Load Employees For Filter
|--------------------------------------------------------------------------
*/

try {

    $employeeStmt = $pdo->query(
        "SELECT
            employee_id,
            employee_code,
            first_name,
            last_name

         FROM employees

         ORDER BY
            first_name,
            last_name"
    );

    $employees = $employeeStmt->fetchAll();

} catch (Throwable $e) {

    error_log(
        'Balance employee filter error: '
        . $e->getMessage()
    );

    $employees = [];
}


/*
|--------------------------------------------------------------------------
| Load Balances
|--------------------------------------------------------------------------
*/

try {

    $sql =
        "SELECT
            lb.balance_id,
            lb.employee_id,
            lb.leave_type_id,
            lb.balance_year,
            lb.allocated_days,
            lb.used_days,
            lb.remaining_days,

            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,
            e.status AS employee_status,

            d.department_name,

            lt.leave_type_name,
            lt.is_paid,
            lt.status AS leave_type_status

         FROM leave_balances lb

         INNER JOIN employees e
            ON lb.employee_id =
               e.employee_id

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         INNER JOIN leave_types lt
            ON lb.leave_type_id =
               lt.leave_type_id

         WHERE lb.balance_year =
            :balance_year";


    $params = [
        'balance_year' =>
            $selectedYear
    ];


    if ($selectedEmployeeId) {

        $sql .=
            " AND lb.employee_id =
                :employee_id";

        $params['employee_id'] =
            $selectedEmployeeId;
    }


    $sql .=
        " ORDER BY
            e.first_name,
            e.last_name,
            lt.leave_type_name";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $balances = $stmt->fetchAll();


} catch (Throwable $e) {

    error_log(
        'Leave balance list error: '
        . $e->getMessage()
    );

    $balances = [];
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalAllocated = 0.0;
$totalUsed = 0.0;
$totalRemaining = 0.0;

$uniqueEmployees = [];


foreach ($balances as $balance) {

    $totalAllocated +=
        (float)$balance[
            'allocated_days'
        ];

    $totalUsed +=
        (float)$balance[
            'used_days'
        ];

    $totalRemaining +=
        (float)$balance[
            'remaining_days'
        ];

    $uniqueEmployees[
        (int)$balance['employee_id']
    ] = true;
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
            Leave Balances
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
                Leave Balance Management
            </h2>

            <p>
                View yearly employee leave
                allocations, usage and remaining
                entitlement.
            </p>

        </div>


        <form
            method="POST"
            action="/admin/initialize-leave-balances.php"
            class="d-flex gap-2"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(
                    generateCsrfToken()
                ) ?>"
            >

            <input
                type="hidden"
                name="balance_year"
                value="<?= $selectedYear ?>"
            >


            <button
                type="submit"
                class="btn-professional-primary"
                onclick="return confirm(
                    'Initialize missing leave balances for <?= $selectedYear ?>?'
                );"
            >

                <i
                    class="bi
                           bi-arrow-clockwise"
                ></i>

                Initialize
                <?= $selectedYear ?>

            </button>

        </form>

    </div>

</div>


<!-- =========================================================
     FILTER
     ========================================================= -->

<div class="admin-card mb-4">

    <div class="admin-card-body">

        <form
            method="GET"
            class="row g-3 align-items-end"
        >

            <div class="col-md-3">

                <label
                    for="year"
                    class="professional-form-label"
                >
                    Leave Year
                </label>

                <select
                    id="year"
                    name="year"
                    class="form-select professional-input"
                >

                    <?php for (
                        $year = $currentYear + 1;
                        $year >= $currentYear - 3;
                        $year--
                    ): ?>

                        <option
                            value="<?= $year ?>"
                            <?= $year === $selectedYear
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= $year ?>
                        </option>

                    <?php endfor; ?>

                </select>

            </div>


            <div class="col-md-6">

                <label
                    for="employee_id"
                    class="professional-form-label"
                >
                    Employee
                </label>

                <select
                    id="employee_id"
                    name="employee_id"
                    class="form-select professional-input"
                >

                    <option value="">
                        All Employees
                    </option>

                    <?php foreach (
                        $employees as $employee
                    ): ?>

                        <option
                            value="<?= (int)$employee[
                                'employee_id'
                            ] ?>"
                            <?= (
                                $selectedEmployeeId
                                ===
                                (int)$employee[
                                    'employee_id'
                                ]
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= escape(
                                $employee[
                                    'employee_code'
                                ]
                                . ' — '
                                . $employee[
                                    'first_name'
                                ]
                                . ' '
                                . $employee[
                                    'last_name'
                                ]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-md-3">

                <button
                    type="submit"
                    class="btn-professional-primary w-100"
                >

                    <i class="bi bi-funnel"></i>

                    Apply Filter

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Employees
                </span>

                <strong>
                    <?= count(
                        $uniqueEmployees
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       purple"
            >

                <i class="bi bi-people"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Allocated
                </span>

                <strong>
                    <?= number_format(
                        $totalAllocated,
                        2
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       blue"
            >

                <i class="bi bi-calendar-plus"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Used
                </span>

                <strong>
                    <?= number_format(
                        $totalUsed,
                        2
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       orange"
            >

                <i
                    class="bi
                           bi-calendar-minus"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Remaining
                </span>

                <strong>
                    <?= number_format(
                        $totalRemaining,
                        2
                    ) ?>
                </strong>

            </div>


            <div
                class="mini-summary-icon
                       green"
            >

                <i
                    class="bi
                           bi-calendar-check"
                ></i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     BALANCE TABLE
     ========================================================= -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                <?= $selectedYear ?>
                Leave Balances
            </h5>

            <small class="text-muted">
                <?= count($balances) ?>
                balance record(s)
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
                        Allocated
                    </th>

                    <th>
                        Used
                    </th>

                    <th>
                        Remaining
                    </th>

                    <th>
                        Usage
                    </th>

                    <th class="text-end">
                        Action
                    </th>

                </tr>

                </thead>


                <tbody>

                <?php if (!$balances): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="text-center py-5"
                        >

                            <div class="empty-state">

                                <i
                                    class="bi
                                           bi-pie-chart"
                                ></i>

                                <h6>
                                    No leave balances
                                </h6>

                                <p>
                                    Initialize missing balances
                                    for <?= $selectedYear ?>.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $balances as $balance
                ): ?>

                    <?php

                    $fullName =
                        $balance['first_name']
                        . ' '
                        . $balance['last_name'];


                    $allocated =
                        (float)$balance[
                            'allocated_days'
                        ];

                    $used =
                        (float)$balance[
                            'used_days'
                        ];

                    $remaining =
                        (float)$balance[
                            'remaining_days'
                        ];


                    $usagePercentage =
                        $allocated > 0
                            ? min(
                                100,
                                (
                                    $used
                                    /
                                    $allocated
                                ) * 100
                            )
                            : 0;

                    ?>


                    <tr>

                        <td>

                            <div
                                class="employee-cell"
                            >

                                <div
                                    class="employee-avatar"
                                >

                                    <?= escape(
                                        strtoupper(
                                            substr(
                                                $balance[
                                                    'first_name'
                                                ],
                                                0,
                                                1
                                            )
                                            .
                                            substr(
                                                $balance[
                                                    'last_name'
                                                ],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>

                                </div>


                                <div
                                    class="employee-info"
                                >

                                    <strong>
                                        <?= escape(
                                            $fullName
                                        ) ?>
                                    </strong>


                                    <span>
                                        <?= escape(
                                            $balance[
                                                'employee_code'
                                            ]
                                        ) ?>
                                    </span>


                                    <small>
                                        <?= escape(
                                            $balance[
                                                'department_name'
                                            ]
                                        ) ?>
                                    </small>

                                </div>

                            </div>

                        </td>


                        <td>

                            <div
                                class="balance-leave-type"
                            >

                                <strong>
                                    <?= escape(
                                        $balance[
                                            'leave_type_name'
                                        ]
                                    ) ?>
                                </strong>

                                <span>

                                    <?= (int)$balance[
                                        'is_paid'
                                    ] === 1
                                        ? 'Paid'
                                        : 'Unpaid'
                                    ?>

                                </span>

                            </div>

                        </td>


                        <td>

                            <strong>
                                <?= number_format(
                                    $allocated,
                                    2
                                ) ?>
                            </strong>

                            <small class="text-muted">
                                days
                            </small>

                        </td>


                        <td>

                            <span class="balance-used">

                                <?= number_format(
                                    $used,
                                    2
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span
                                class="balance-remaining"
                            >

                                <?= number_format(
                                    $remaining,
                                    2
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <div
                                class="balance-progress-wrapper"
                            >

                                <div
                                    class="balance-progress"
                                >

                                    <div
                                        class="balance-progress-bar"
                                        style="width:
                                        <?= number_format(
                                            $usagePercentage,
                                            2,
                                            '.',
                                            ''
                                        ) ?>%;"
                                    ></div>

                                </div>

                                <small>

                                    <?= number_format(
                                        $usagePercentage,
                                        0
                                    ) ?>%

                                </small>

                            </div>

                        </td>


                        <td class="text-end">

                            <a
                                href="/admin/edit-leave-balance.php?id=<?= (int)$balance['balance_id'] ?>"
                                class="btn
                                       btn-sm
                                       btn-outline-primary"
                            >

                                <i class="bi bi-pencil"></i>

                                Adjust

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