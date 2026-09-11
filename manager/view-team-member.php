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
    'Team Member Details';

$currentYear =
    (int)date('Y');


/*
|--------------------------------------------------------------------------
| Employee ID
|--------------------------------------------------------------------------
*/

$employeeId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (!$employeeId) {

    setFlash(
        'danger',
        'Invalid employee selected.'
    );

    header(
        'Location: /manager/my-team.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Manager
|--------------------------------------------------------------------------
*/

$managerStmt =
    $pdo->prepare(
        "SELECT
            employee_id,
            first_name,
            last_name

         FROM employees

         WHERE user_id =
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
| Load Team Member
|--------------------------------------------------------------------------
|
| SECURITY:
| manager_id restriction prevents a Manager from viewing another
| Manager's employee simply by changing ?id= in the URL.
|--------------------------------------------------------------------------
*/

$memberStmt =
    $pdo->prepare(
        "SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.phone,
            e.job_title,
            e.date_joined,
            e.status,

            u.email,
            u.status AS user_status,

            d.department_name

         FROM employees e

         INNER JOIN users u
            ON e.user_id =
               u.user_id

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         WHERE
            e.employee_id =
                :employee_id

            AND e.manager_id =
                :manager_id

         LIMIT 1"
    );


$memberStmt->execute([

    'employee_id' =>
        $employeeId,

    'manager_id' =>
        $managerId
]);


$teamMember =
    $memberStmt->fetch();


if (!$teamMember) {

    http_response_code(404);

    exit(
        'Team member not found or access denied.'
    );
}


/*
|--------------------------------------------------------------------------
| Current Year Leave Balances
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
| Balance Totals
|--------------------------------------------------------------------------
*/

$totalAllocated = 0.00;
$totalUsed = 0.00;
$totalRemaining = 0.00;


foreach (
    $balances
    as $balance
) {

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


/*
|--------------------------------------------------------------------------
| Application Counters
|--------------------------------------------------------------------------
*/

$countStmt =
    $pdo->prepare(
        "SELECT
            COUNT(*) AS total_requests,

            COALESCE(
                SUM(
                    CASE
                        WHEN status =
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
                        WHEN status =
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
                        WHEN status =
                            'Rejected'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS rejected_requests

         FROM leave_applications

         WHERE employee_id =
            :employee_id"
    );


$countStmt->execute([
    'employee_id' =>
        $employeeId
]);


$requestCounts =
    $countStmt->fetch();


/*
|--------------------------------------------------------------------------
| Recent Leave Applications
|--------------------------------------------------------------------------
*/

$applicationStmt =
    $pdo->prepare(
        "SELECT
            la.application_id,
            la.start_date,
            la.end_date,
            la.number_of_days,
            la.status,
            la.applied_at,

            lt.leave_type_name,

            lap.decision,
            lap.decision_date

         FROM leave_applications la

         INNER JOIN leave_types lt
            ON la.leave_type_id =
               lt.leave_type_id

         LEFT JOIN leave_approvals lap
            ON la.application_id =
               lap.application_id

         WHERE la.employee_id =
            :employee_id

         ORDER BY
            la.applied_at DESC

         LIMIT 8"
    );


$applicationStmt->execute([
    'employee_id' =>
        $employeeId
]);


$recentApplications =
    $applicationStmt->fetchAll();


require_once __DIR__
    . '/../includes/manager/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/manager/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <a href="/manager/my-team.php">
            My Team
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            <?= escape(
                $teamMember[
                    'employee_code'
                ]
            ) ?>
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
                Team Member Details
            </h2>

            <p>
                View employee information,
                current leave balances and
                recent leave activity.
            </p>

        </div>


        <a
            href="/manager/team-reports.php?employee_id=<?= $employeeId ?>&year=<?= $currentYear ?>"
            class="btn-professional-secondary"
        >

            <i class="bi bi-bar-chart-fill"></i>

            View Employee Report

        </a>

    </div>

</div>


<!-- =========================================================
     PROFILE HERO
     ========================================================= -->

<div class="team-member-profile-card">

    <div class="team-member-profile-main">

        <div class="team-member-large-avatar">

            <?= escape(
                strtoupper(
                    substr(
                        $teamMember[
                            'first_name'
                        ],
                        0,
                        1
                    )
                    .
                    substr(
                        $teamMember[
                            'last_name'
                        ],
                        0,
                        1
                    )
                )
            ) ?>

        </div>


        <div>

            <span
                class="team-member-label"
            >
                TEAM MEMBER
            </span>


            <h2>

                <?= escape(
                    $teamMember[
                        'first_name'
                    ]
                    . ' '
                    . $teamMember[
                        'last_name'
                    ]
                ) ?>

            </h2>


            <p>

                <?= escape(
                    $teamMember[
                        'employee_code'
                    ]
                ) ?>

                ·

                <?= escape(
                    $teamMember[
                        'job_title'
                    ]
                ) ?>

            </p>


            <div
                class="team-member-profile-meta"
            >

                <span>

                    <i class="bi bi-building"></i>

                    <?= escape(
                        $teamMember[
                            'department_name'
                        ]
                    ) ?>

                </span>


                <span>

                    <i
                        class="bi
                               bi-envelope"
                    ></i>

                    <?= escape(
                        $teamMember[
                            'email'
                        ]
                    ) ?>

                </span>


                <?php if (
                    !empty(
                        $teamMember[
                            'phone'
                        ]
                    )
                ): ?>

                    <span>

                        <i
                            class="bi
                                   bi-telephone"
                        ></i>

                        <?= escape(
                            $teamMember[
                                'phone'
                            ]
                        ) ?>

                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <div>

        <?php if (
            $teamMember[
                'status'
            ] === 'Active'
        ): ?>

            <span
                class="status-badge
                       status-active"
            >

                <i
                    class="bi
                           bi-check-circle-fill"
                ></i>

                Active Employee

            </span>

        <?php else: ?>

            <span
                class="status-badge
                       status-inactive"
            >

                <i
                    class="bi
                           bi-dash-circle-fill"
                ></i>

                Inactive Employee

            </span>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     KPI CARDS
     ========================================================= -->

<div class="row g-3 my-4">

    <div class="col-sm-6 col-xl-3">

        <div class="employee-kpi-card">

            <div class="employee-kpi-top">

                <div class="employee-kpi-icon">

                    <i
                        class="bi
                               bi-calendar-plus"
                    ></i>

                </div>

            </div>


            <span
                class="employee-kpi-label"
            >
                Allocated
            </span>


            <div
                class="employee-kpi-value"
            >

                <?= number_format(
                    $totalAllocated,
                    2
                ) ?>

                <small>
                    days
                </small>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="employee-kpi-card">

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon
                           manager-pending-icon"
                >

                    <i
                        class="bi
                               bi-calendar-minus"
                    ></i>

                </div>

            </div>


            <span
                class="employee-kpi-label"
            >
                Used
            </span>


            <div
                class="employee-kpi-value"
            >

                <?= number_format(
                    $totalUsed,
                    2
                ) ?>

                <small>
                    days
                </small>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="employee-kpi-card">

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon
                           manager-approved-icon"
                >

                    <i
                        class="bi
                               bi-calendar-check"
                    ></i>

                </div>

            </div>


            <span
                class="employee-kpi-label"
            >
                Remaining
            </span>


            <div
                class="employee-kpi-value"
            >

                <?= number_format(
                    $totalRemaining,
                    2
                ) ?>

                <small>
                    days
                </small>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="employee-kpi-card">

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon
                           manager-pending-icon"
                >

                    <i
                        class="bi
                               bi-hourglass-split"
                    ></i>

                </div>

            </div>


            <span
                class="employee-kpi-label"
            >
                Pending Requests
            </span>


            <div
                class="employee-kpi-value"
            >

                <?= (int)(
                    $requestCounts[
                        'pending_requests'
                    ]
                    ?? 0
                ) ?>

            </div>

        </div>

    </div>

</div>


<div class="row g-4">

    <!-- =====================================================
         BALANCES
         ===================================================== -->

    <div class="col-xl-8">

        <div
            class="admin-card
                   mb-4"
        >

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        <?= $currentYear ?>
                        BALANCES
                    </div>

                    <h5>
                        Leave Balances
                    </h5>

                    <small class="text-muted">
                        Current yearly entitlement
                        and usage.
                    </small>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-pie-chart-fill"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <?php if (
                    !$balances
                ): ?>

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
                            No balances available
                        </h6>


                        <p>
                            Leave balances have not been
                            initialized for
                            <?= $currentYear ?>.
                        </p>

                    </div>


                <?php else: ?>

                    <div
                        class="row g-3"
                    >

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
                                    class="team-member-balance-card"
                                >

                                    <div
                                        class="team-balance-header"
                                    >

                                        <div
                                            class="premium-leave-icon"
                                        >

                                            <i
                                                class="bi
                                                       bi-calendar-event"
                                            ></i>

                                        </div>


                                        <div>

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
                                                    ? 'Paid Leave'
                                                    : 'Unpaid Leave'
                                                ?>

                                            </span>

                                        </div>

                                    </div>


                                    <div
                                        class="team-balance-remaining"
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
                                        class="premium-progress"
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


        <!-- =================================================
             RECENT APPLICATIONS
             ================================================= -->

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        LEAVE HISTORY
                    </div>

                    <h5>
                        Recent Leave Requests
                    </h5>

                    <small class="text-muted">
                        Latest applications submitted
                        by this employee.
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
                                    Leave Type
                                </th>

                                <th>
                                    Period
                                </th>

                                <th>
                                    Days
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

                        <?php if (
                            !$recentApplications
                        ): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center py-5"
                                >

                                    <div
                                        class="employee-empty-state"
                                    >

                                        <h6>
                                            No leave requests
                                        </h6>

                                        <p>
                                            This employee has not
                                            submitted any leave
                                            applications yet.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>


                        <?php foreach (
                            $recentApplications
                            as $application
                        ): ?>

                            <tr>

                                <td>

                                    <strong
                                        class="admin-record-type"
                                    >

                                        <?= escape(
                                            $application[
                                                'leave_type_name'
                                            ]
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= escape(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $application[
                                                    'start_date'
                                                ]
                                            )
                                        )
                                    ) ?>


                                    <small
                                        class="d-block
                                               text-muted"
                                    >

                                        to

                                        <?= escape(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $application[
                                                        'end_date'
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    </small>

                                </td>


                                <td>

                                    <?= number_format(
                                        (float)$application[
                                            'number_of_days'
                                        ],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="leave-status-badge
                                               leave-status-<?= strtolower(
                                                   $application[
                                                       'status'
                                                   ]
                                               ) ?>"
                                    >

                                        <?= escape(
                                            $application[
                                                'status'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= escape(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $application[
                                                    'applied_at'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td class="text-end">

                                    <a
                                        href="/manager/view-request.php?id=<?= (int)$application['application_id'] ?>"
                                        class="btn
                                               btn-sm
                                               btn-outline-primary"
                                    >

                                        <i
                                            class="bi
                                                   bi-eye"
                                        ></i>

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

    </div>


    <!-- =====================================================
         EMPLOYMENT INFORMATION
         ===================================================== -->

    <div class="col-xl-4">

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        EMPLOYMENT
                    </div>

                    <h5>
                        Employee Information
                    </h5>

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
                                    $teamMember[
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
                                    $teamMember[
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
                                    $teamMember[
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
                                            $teamMember[
                                                'date_joined'
                                            ]
                                        )
                                    )
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
                                Employee Status
                            </span>

                            <strong>

                                <?= escape(
                                    $teamMember[
                                        'status'
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-shield-check"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Account Status
                            </span>

                            <strong>

                                <?= escape(
                                    $teamMember[
                                        'user_status'
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- REQUEST SUMMARY -->

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        REQUEST SUMMARY
                    </div>

                    <h5>
                        Leave Activity
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <div
                    class="team-request-summary"
                >

                    <div>

                        <span>
                            Total
                        </span>

                        <strong>

                            <?= (int)(
                                $requestCounts[
                                    'total_requests'
                                ]
                                ?? 0
                            ) ?>

                        </strong>

                    </div>


                    <div class="pending">

                        <span>
                            Pending
                        </span>

                        <strong>

                            <?= (int)(
                                $requestCounts[
                                    'pending_requests'
                                ]
                                ?? 0
                            ) ?>

                        </strong>

                    </div>


                    <div class="approved">

                        <span>
                            Approved
                        </span>

                        <strong>

                            <?= (int)(
                                $requestCounts[
                                    'approved_requests'
                                ]
                                ?? 0
                            ) ?>

                        </strong>

                    </div>


                    <div class="rejected">

                        <span>
                            Rejected
                        </span>

                        <strong>

                            <?= (int)(
                                $requestCounts[
                                    'rejected_requests'
                                ]
                                ?? 0
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div
                    class="admin-info-box
                           mt-4"
                >

                    <div
                        class="admin-info-icon"
                    >

                        <i
                            class="bi
                                   bi-lock-fill"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Read-only profile
                        </strong>

                        <p>
                            Employee account and
                            employment information can
                            only be changed by an
                            Administrator.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/manager/footer.php';