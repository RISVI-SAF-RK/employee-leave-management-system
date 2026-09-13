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
     PREMIUM EMPLOYEE HERO
     ========================================================= -->

<section class="employee-dashboard-hero">

    <div class="employee-dashboard-hero-content">

        <div class="employee-dashboard-eyebrow">
            <i class="bi bi-calendar2-check-fill"></i>

            <?= $currentYear ?> Leave Workspace
        </div>


        <h2>
            Welcome back,
            <?= escape(
                $employeeProfile['first_name']
            ) ?>
            <span>👋</span>
        </h2>


        <p>
            View your leave entitlement, track usage
            and manage your leave information from
            one secure workspace.
        </p>


        <div class="employee-hero-meta">

            <span>
                <i class="bi bi-person-badge"></i>

                <?= escape(
                    $employeeProfile[
                        'employee_code'
                    ]
                ) ?>
            </span>


            <span>
                <i class="bi bi-building"></i>

                <?= escape(
                    $employeeProfile[
                        'department_name'
                    ]
                ) ?>
            </span>


            <span>
                <i class="bi bi-briefcase"></i>

                <?= escape(
                    $employeeProfile[
                        'job_title'
                    ]
                ) ?>
            </span>

        </div>


        <div class="employee-hero-actions">

            <a
    href="/employee/apply-leave.php"
    class="employee-primary-action"
>

    <i class="bi bi-calendar-plus-fill"></i>

    Apply for Leave

</a> 

            <a
                href="/employee/my-balances.php"
                class="employee-secondary-action"
            >

                <i class="bi bi-pie-chart-fill"></i>

                View My Balances

            </a>

        </div>

    </div>


    <div class="employee-dashboard-hero-art">

        <div class="hero-art-circle hero-art-one"></div>

        <div class="hero-art-circle hero-art-two"></div>

        <div class="hero-calendar-art">

            <i class="bi bi-calendar2-week-fill"></i>

        </div>

    </div>

</section>


<!-- =========================================================
     KPI CARDS
     ========================================================= -->

<div class="row g-4 mb-4">

    <!-- ALLOCATED -->

    <div class="col-md-4">

        <div
            class="employee-kpi-card
                   employee-kpi-purple"
        >

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon"
                >

                    <i
                        class="bi
                               bi-calendar-plus"
                    ></i>

                </div>


                <span
                    class="employee-kpi-tag"
                >
                    <?= $currentYear ?>
                </span>

            </div>


            <span class="employee-kpi-label">
                Total Allocated
            </span>


            <div class="employee-kpi-value">

                <?= number_format(
                    $totalAllocated,
                    2
                ) ?>

                <small>
                    days
                </small>

            </div>


            <div class="employee-kpi-footer">

                <i
                    class="bi
                           bi-info-circle"
                ></i>

                Total yearly entitlement

            </div>

        </div>

    </div>


    <!-- USED -->

    <div class="col-md-4">

        <div
            class="employee-kpi-card
                   employee-kpi-orange"
        >

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon"
                >

                    <i
                        class="bi
                               bi-calendar-minus"
                    ></i>

                </div>


                <span
                    class="employee-kpi-tag"
                >
                    Used
                </span>

            </div>


            <span class="employee-kpi-label">
                Leave Used
            </span>


            <div class="employee-kpi-value">

                <?= number_format(
                    $totalUsed,
                    2
                ) ?>

                <small>
                    days
                </small>

            </div>


            <div class="employee-kpi-footer">

                <i
                    class="bi
                           bi-check-circle"
                ></i>

                Approved leave only

            </div>

        </div>

    </div>


    <!-- REMAINING -->

    <div class="col-md-4">

        <div
            class="employee-kpi-card
                   employee-kpi-green"
        >

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon"
                >

                    <i
                        class="bi
                               bi-calendar-check"
                    ></i>

                </div>


                <span
                    class="employee-kpi-tag"
                >
                    Available
                </span>

            </div>


            <span class="employee-kpi-label">
                Total Remaining
            </span>


            <div class="employee-kpi-value">

                <?= number_format(
                    $totalRemaining,
                    2
                ) ?>

                <small>
                    days
                </small>

            </div>


            <div class="employee-kpi-footer">

                <i
                    class="bi
                           bi-lightning-charge"
                ></i>

                Current available balance

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     MAIN DASHBOARD GRID
     ========================================================= -->

<div class="row g-4">

    <!-- LEAVE BALANCES -->

    <div class="col-xl-8">

        <div
            class="admin-card
                   employee-dashboard-card"
        >

            <div
                class="admin-card-header
                       employee-dashboard-card-header"
            >

                <div>

                    <div
                        class="employee-section-label"
                    >
                        LEAVE OVERVIEW
                    </div>


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
                    class="employee-view-all"
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

                    <div class="employee-empty-state">

                        <div class="employee-empty-icon">

                            <i
                                class="bi
                                       bi-calendar-x"
                            ></i>

                        </div>


                        <h6>
                            No leave balances available
                        </h6>


                        <p>
                            Your <?= $currentYear ?>
                            leave balances have not
                            been initialized yet.
                        </p>

                    </div>


                <?php else: ?>

                    <div class="row g-3">

                        <?php foreach (
                            $balances
                            as $balance
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
                                            $used /
                                            $allocated
                                        ) * 100
                                    )
                                    : 0;

                            ?>


                            <div class="col-lg-6">

                                <div
                                    class="premium-balance-card"
                                >

                                    <div
                                        class="premium-balance-header"
                                    >

                                        <div
                                            class="premium-leave-icon"
                                        >

                                            <i
                                                class="bi
                                                       bi-calendar-event"
                                            ></i>

                                        </div>


                                        <div
                                            class="premium-leave-name"
                                        >

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


                                        <span
                                            class="premium-payment-badge"
                                        >

                                            <?= (int)$balance[
                                                'is_paid'
                                            ] === 1
                                                ? 'Paid'
                                                : 'Unpaid'
                                            ?>

                                        </span>

                                    </div>


                                    <div
                                        class="premium-balance-main"
                                    >

                                        <div>

                                            <span>
                                                Available Balance
                                            </span>


                                            <strong>

                                                <?= number_format(
                                                    $remaining,
                                                    2
                                                ) ?>

                                                <small>
                                                    days
                                                </small>

                                            </strong>

                                        </div>


                                        <div
                                            class="premium-balance-percent"
                                        >

                                            <?= number_format(
                                                $usagePercentage,
                                                0
                                            ) ?>%

                                            <span>
                                                used
                                            </span>

                                        </div>

                                    </div>


                                    <div
                                        class="premium-progress"
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
                                        class="premium-balance-stats"
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

                                            <strong
                                                class="remaining-value"
                                            >
                                                <?= number_format(
                                                    $remaining,
                                                    2
                                                ) ?>
                                            </strong>

                                        </div>

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
         RIGHT SIDEBAR INFORMATION
         ===================================================== -->

    <div class="col-xl-4">

        <!-- EMPLOYMENT DETAILS -->

        <div
            class="admin-card
                   employee-dashboard-card
                   mb-4"
        >

            <div
                class="admin-card-header
                       employee-dashboard-card-header"
            >

                <div>

                    <div
                        class="employee-section-label"
                    >
                        MY PROFILE
                    </div>


                    <h5>
                        Employment Details
                    </h5>


                    <small class="text-muted">
                        Current work information
                    </small>

                </div>


                <div
                    class="header-icon-box"
                >

                    <i
                        class="bi
                               bi-person-vcard-fill"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <div
                    class="premium-profile-list"
                >

                    <!-- EMPLOYEE CODE -->

                    <div
                        class="premium-profile-item"
                    >

                        <div
                            class="premium-profile-icon"
                        >

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


                    <!-- DEPARTMENT -->

                    <div
                        class="premium-profile-item"
                    >

                        <div
                            class="premium-profile-icon"
                        >

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


                    <!-- JOB TITLE -->

                    <div
                        class="premium-profile-item"
                    >

                        <div
                            class="premium-profile-icon"
                        >

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


                    <!-- MANAGER -->

                    <div
                        class="premium-profile-item"
                    >

                        <div
                            class="premium-profile-icon"
                        >

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


                    <!-- DATE JOINED -->

                    <div
                        class="premium-profile-item"
                    >

                        <div
                            class="premium-profile-icon"
                        >

                            <i
                                class="bi
                                       bi-calendar-check"
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


        <!-- QUICK ACTIONS -->

        <div
            class="admin-card
                   employee-dashboard-card"
        >

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        SHORTCUTS
                    </div>

                    <h5>
                        Quick Actions
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <div
                    class="employee-quick-actions"
                >

                    <a
                        href="/employee/my-balances.php"
                        class="employee-quick-action"
                    >

                        <div>

                            <i
                                class="bi
                                       bi-pie-chart-fill"
                            ></i>

                        </div>


                        <span>

                            <strong>
                                My Balances
                            </strong>

                            <small>
                                View leave entitlement
                            </small>

                        </span>


                        <i
                            class="bi
                                   bi-chevron-right"
                        ></i>

                    </a>


                    <a
                        href="/employee/apply-leave.php"
                        class="employee-quick-action"
                    >

                        <div>

                            <i
                                class="bi
                                       bi-calendar-plus-fill"
                            ></i>

                        </div>


                        <span>

                            <strong>
                                Apply for Leave
                            </strong>

                            <small>
                                Submit a new leave request
                            </small>

                        </span>


                        <i
                            class="bi
                                   bi-chevron-right"
                        ></i>

                    </a>


                    <a
                        href="/employee/leave-history.php"
                        class="employee-quick-action"
                    >

                        <div>

                            <i
                                class="bi
                                       bi-clock-history"
                            ></i>

                        </div>


                        <span>

                            <strong>
                                Leave History
                            </strong>

                            <small>
                                Track your leave requests
                            </small>

                        </span>


                        <i
                            class="bi
                                   bi-chevron-right"
                        ></i>

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';