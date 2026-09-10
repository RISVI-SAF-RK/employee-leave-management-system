<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


$pageTitle = 'Dashboard';

$currentYear = (int)date('Y');


/*
|--------------------------------------------------------------------------
| Load Current Employee
|--------------------------------------------------------------------------
*/

$profileStmt =
    $pdo->prepare(
        "SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.phone,
            e.job_title,
            e.date_joined,
            e.manager_id,
            e.status,

            d.department_name,

            CONCAT(
                m.first_name,
                ' ',
                m.last_name
            ) AS manager_name

         FROM employees e

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         LEFT JOIN employees m
            ON e.manager_id =
               m.employee_id

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
| Load Current Year Leave Balances
|--------------------------------------------------------------------------
*/

$balanceStmt =
    $pdo->prepare(
        "SELECT
            lb.balance_id,
            lb.allocated_days,
            lb.used_days,
            lb.remaining_days,

            lt.leave_type_name,
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
        $currentYear
]);


$balances =
    $balanceStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$totalAllocated = 0.00;
$totalUsed = 0.00;
$totalRemaining = 0.00;


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
}


require_once __DIR__
    . '/../includes/employee/header.php';
?>


<!-- =========================================================
     WELCOME HERO
     ========================================================= -->

<section class="employee-hero">

    <div class="employee-hero-content">

        <div class="employee-hero-label">

            <i
                class="bi
                       bi-calendar2-check"
            ></i>

            <?= $currentYear ?>
            Leave Management

        </div>


        <h2>

            Welcome back,
            <?= escape(
                $employeeProfile[
                    'first_name'
                ]
            ) ?>

        </h2>


        <p>
            Manage your leave,
            monitor your available balances
            and track requests from one place.
        </p>


        <div
            class="d-flex
                   flex-wrap
                   gap-2
                   mt-4"
        >

            <span
                class="employee-hero-badge"
            >

                <i
                    class="bi
                           bi-person-badge"
                ></i>

                <?= escape(
                    $employeeProfile[
                        'employee_code'
                    ]
                ) ?>

            </span>


            <span
                class="employee-hero-badge"
            >

                <i
                    class="bi
                           bi-building"
                ></i>

                <?= escape(
                    $employeeProfile[
                        'department_name'
                    ]
                ) ?>

            </span>


            <span
                class="employee-hero-badge"
            >

                <i
                    class="bi
                           bi-briefcase"
                ></i>

                <?= escape(
                    $employeeProfile[
                        'job_title'
                    ]
                ) ?>

            </span>

        </div>

    </div>


    <div class="employee-hero-decoration">

        <i
            class="bi
                   bi-calendar2-week"
        ></i>

    </div>

</section>


<!-- =========================================================
     SUMMARY CARDS
     ========================================================= -->

<div class="row g-3 mt-1 mb-4">

    <div class="col-sm-6 col-xl-4">

        <div class="employee-summary-card">

            <div>

                <span>
                    Total Allocated
                </span>

                <strong>
                    <?= number_format(
                        $totalAllocated,
                        2
                    ) ?>
                </strong>

                <small>
                    days in <?= $currentYear ?>
                </small>

            </div>


            <div
                class="employee-summary-icon
                       allocation"
            >

                <i
                    class="bi
                           bi-calendar-plus"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-4">

        <div class="employee-summary-card">

            <div>

                <span>
                    Leave Used
                </span>

                <strong>
                    <?= number_format(
                        $totalUsed,
                        2
                    ) ?>
                </strong>

                <small>
                    approved leave
                </small>

            </div>


            <div
                class="employee-summary-icon
                       used"
            >

                <i
                    class="bi
                           bi-calendar-minus"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-4">

        <div class="employee-summary-card">

            <div>

                <span>
                    Total Remaining
                </span>

                <strong>
                    <?= number_format(
                        $totalRemaining,
                        2
                    ) ?>
                </strong>

                <small>
                    available days
                </small>

            </div>


            <div
                class="employee-summary-icon
                       remaining"
            >

                <i
                    class="bi
                           bi-calendar-check"
                ></i>

            </div>

        </div>

    </div>

</div>


<div class="row g-4">

    <!-- =====================================================
         BALANCE PREVIEW
         ===================================================== -->

    <div class="col-xl-8">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <h5>
                        My Leave Balances
                    </h5>

                    <small class="text-muted">
                        <?= $currentYear ?>
                        entitlement overview
                    </small>

                </div>


                <a
                    href="/employee/my-balances.php"
                    class="employee-text-link"
                >

                    View All

                    <i
                        class="bi
                               bi-arrow-right"
                    ></i>

                </a>

            </div>


            <div class="admin-card-body">

                <?php if (!$balances): ?>

                    <div
                        class="employee-empty-state"
                    >

                        <div
                            class="employee-empty-icon"
                        >

                            <i
                                class="bi
                                       bi-pie-chart"
                            ></i>

                        </div>


                        <h6>
                            No leave balances available
                        </h6>


                        <p>
                            Your leave balances have not
                            been initialized for
                            <?= $currentYear ?> yet.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="row g-3">

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


                            $percentage =
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


                            <div class="col-md-6">

                                <div
                                    class="employee-balance-card"
                                >

                                    <div
                                        class="employee-balance-top"
                                    >

                                        <div>

                                            <span>
                                                Leave Type
                                            </span>

                                            <strong>

                                                <?= escape(
                                                    $balance[
                                                        'leave_type_name'
                                                    ]
                                                ) ?>

                                            </strong>

                                        </div>


                                        <div
                                            class="employee-balance-icon"
                                        >

                                            <i
                                                class="bi
                                                       bi-calendar-event"
                                            ></i>

                                        </div>

                                    </div>


                                    <div
                                        class="employee-balance-number"
                                    >

                                        <?= number_format(
                                            $remaining,
                                            2
                                        ) ?>

                                        <small>
                                            days left
                                        </small>

                                    </div>


                                    <div
                                        class="employee-balance-progress"
                                    >

                                        <div
                                            style="width:
                                            <?= number_format(
                                                $percentage,
                                                2,
                                                '.',
                                                ''
                                            ) ?>%;"
                                        ></div>

                                    </div>


                                    <div
                                        class="employee-balance-footer"
                                    >

                                        <span>
                                            Used
                                            <strong>
                                                <?= number_format(
                                                    $used,
                                                    2
                                                ) ?>
                                            </strong>
                                        </span>


                                        <span>
                                            Allocated
                                            <strong>
                                                <?= number_format(
                                                    $allocated,
                                                    2
                                                ) ?>
                                            </strong>
                                        </span>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         EMPLOYEE INFORMATION
         ===================================================== -->

    <div class="col-xl-4">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Employment Details
                    </h5>

                    <small class="text-muted">
                        Your current information
                    </small>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-person-vcard"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="detail-list">

                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-person-badge"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Employee Code
                            </span>

                            <strong>

                                <?= escape(
                                    $employeeProfile[
                                        'employee_code'
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-building"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Department
                            </span>

                            <strong>

                                <?= escape(
                                    $employeeProfile[
                                        'department_name'
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-briefcase"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Job Title
                            </span>

                            <strong>

                                <?= escape(
                                    $employeeProfile[
                                        'job_title'
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-person-check"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Reporting Manager
                            </span>

                            <strong>

                                <?= !empty(
                                    $employeeProfile[
                                        'manager_name'
                                    ]
                                )
                                    ? escape(
                                        $employeeProfile[
                                            'manager_name'
                                        ]
                                    )
                                    : 'Not Assigned'
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-calendar-plus"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Date Joined
                            </span>

                            <strong>

                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $employeeProfile[
                                                'date_joined'
                                            ]
                                        )
                                    )
                                ) ?>

                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';