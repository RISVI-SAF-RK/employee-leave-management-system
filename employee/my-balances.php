<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


$pageTitle =
    'My Leave Balances';


$currentYear =
    (int)date('Y');


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

    $selectedYear =
        $currentYear;
}


/*
|--------------------------------------------------------------------------
| Current Employee
|--------------------------------------------------------------------------
*/

$profileStmt =
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


$profileStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$employeeProfile =
    $profileStmt->fetch();


if (!$employeeProfile) {

    http_response_code(403);

    exit(
        'Employee profile not found.'
    );
}


$employeeId =
    (int)$employeeProfile[
        'employee_id'
    ];


/*
|--------------------------------------------------------------------------
| Only This Employee's Balances
|--------------------------------------------------------------------------
*/

$balanceStmt =
    $pdo->prepare(
        "SELECT
            lb.balance_id,
            lb.balance_year,
            lb.allocated_days,
            lb.used_days,
            lb.remaining_days,

            lt.leave_type_name,
            lt.description,
            lt.is_paid

         FROM leave_balances lb

         INNER JOIN leave_types lt
            ON lb.leave_type_id =
               lt.leave_type_id

         WHERE
            lb.employee_id =
                :employee_id

            AND lb.balance_year =
                :balance_year

         ORDER BY
            lt.leave_type_name"
    );


$balanceStmt->execute([

    'employee_id' =>
        $employeeId,

    'balance_year' =>
        $selectedYear
]);


$balances =
    $balanceStmt->fetchAll();


require_once __DIR__
    . '/../includes/employee/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/employee/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            My Balances
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
                My Leave Balances
            </h2>

            <p>
                View your allocated,
                used and remaining leave.
            </p>

        </div>


        <form method="GET">

            <select
                name="year"
                class="form-select
                       professional-input"
                onchange="this.form.submit()"
            >

                <?php for (
                    $year = $currentYear + 1;
                    $year >= $currentYear - 3;
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

        </form>

    </div>

</div>


<?php if (!$balances): ?>

    <div class="admin-card">

        <div class="admin-card-body">

            <div class="employee-empty-state">

                <div class="employee-empty-icon">

                    <i
                        class="bi
                               bi-calendar-x"
                    ></i>

                </div>


                <h5>
                    No balances for
                    <?= $selectedYear ?>
                </h5>


                <p>
                    Your leave entitlement
                    has not been initialized
                    for this year.
                </p>

            </div>

        </div>

    </div>


<?php else: ?>


    <div class="row g-4">

        <?php foreach (
            $balances as $balance
        ): ?>

            <?php

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


            <div
                class="col-md-6
                       col-xl-4"
            >

                <div
                    class="employee-balance-large-card"
                >

                    <div
                        class="employee-balance-large-header"
                    >

                        <div
                            class="employee-balance-icon"
                        >

                            <i
                                class="bi
                                       bi-calendar2-week"
                            ></i>

                        </div>


                        <div>

                            <h5>

                                <?= escape(
                                    $balance[
                                        'leave_type_name'
                                    ]
                                ) ?>

                            </h5>


                            <span>

                                <?= (int)$balance[
                                    'is_paid'
                                ] === 1
                                    ? 'Paid Leave'
                                    : 'Unpaid Leave'
                                ?>

                            </span>

                        </div>

                    </div>


                    <div
                        class="employee-remaining-display"
                    >

                        <strong>

                            <?= number_format(
                                $remaining,
                                2
                            ) ?>

                        </strong>

                        <span>
                            days remaining
                        </span>

                    </div>


                    <div
                        class="employee-balance-progress"
                    >

                        <div
                            style="width:
                            <?= number_format(
                                $usagePercentage,
                                2,
                                '.',
                                ''
                            ) ?>%;"
                        ></div>

                    </div>


                    <div
                        class="employee-balance-stat-grid"
                    >

                        <div>

                            <span>
                                Allocated
                            </span>

                            <strong>

                                <?= number_format(
                                    $allocated,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Used
                            </span>

                            <strong>

                                <?= number_format(
                                    $used,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Remaining
                            </span>

                            <strong>

                                <?= number_format(
                                    $remaining,
                                    2
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <?php if (
                        !empty(
                            $balance[
                                'description'
                            ]
                        )
                    ): ?>

                        <p
                            class="employee-balance-description"
                        >

                            <?= escape(
                                $balance[
                                    'description'
                                ]
                            ) ?>

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';