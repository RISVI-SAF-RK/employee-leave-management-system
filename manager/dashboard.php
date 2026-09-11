<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$pageTitle = 'Dashboard';


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
            e.date_joined,

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
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$teamStmt =
    $pdo->prepare(
        "SELECT COUNT(*)

         FROM employees

         WHERE
            manager_id =
                :manager_id

            AND status =
                'Active'"
    );


$teamStmt->execute([
    'manager_id' =>
        $managerId
]);


$teamCount =
    (int)$teamStmt
        ->fetchColumn();


$countStmt =
    $pdo->prepare(
        "SELECT
            COUNT(*) AS total_requests,

            SUM(
                la.status = 'Pending'
            ) AS pending_requests,

            SUM(
                la.status = 'Approved'
            ) AS approved_requests,

            SUM(
                la.status = 'Rejected'
            ) AS rejected_requests

         FROM leave_applications la

         INNER JOIN employees e
            ON la.employee_id =
               e.employee_id

         WHERE e.manager_id =
            :manager_id"
    );


$countStmt->execute([
    'manager_id' =>
        $managerId
]);


$counts =
    $countStmt->fetch();


/*
|--------------------------------------------------------------------------
| Recent Pending Requests
|--------------------------------------------------------------------------
*/

$pendingStmt =
    $pdo->prepare(
        "SELECT
            la.application_id,
            la.start_date,
            la.end_date,
            la.number_of_days,
            la.applied_at,

            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,

            lt.leave_type_name

         FROM leave_applications la

         INNER JOIN employees e
            ON la.employee_id =
               e.employee_id

         INNER JOIN leave_types lt
            ON la.leave_type_id =
               lt.leave_type_id

         WHERE
            e.manager_id =
                :manager_id

            AND la.status =
                'Pending'

         ORDER BY
            la.applied_at ASC

         LIMIT 6"
    );


$pendingStmt->execute([
    'manager_id' =>
        $managerId
]);


$pendingRequests =
    $pendingStmt->fetchAll();


require_once __DIR__
    . '/../includes/manager/header.php';
?>


<section class="manager-dashboard-hero">

    <div>

        <div class="manager-hero-label">

            <i class="bi bi-person-check-fill"></i>

            Team Leave Management

        </div>


        <h2>

            Welcome back,
            <?= escape(
                $managerProfile[
                    'first_name'
                ]
            ) ?>

        </h2>


        <p>
            Review employee leave requests,
            monitor your team and make
            approval decisions efficiently.
        </p>


        <div class="manager-hero-meta">

            <span>

                <i class="bi bi-person-badge"></i>

                <?= escape(
                    $managerProfile[
                        'employee_code'
                    ]
                ) ?>

            </span>


            <span>

                <i class="bi bi-building"></i>

                <?= escape(
                    $managerProfile[
                        'department_name'
                    ]
                ) ?>

            </span>


            <span>

                <i class="bi bi-briefcase"></i>

                <?= escape(
                    $managerProfile[
                        'job_title'
                    ]
                ) ?>

            </span>

        </div>


        <div class="manager-hero-actions">

            <a
                href="/manager/pending-requests.php?status=Pending"
                class="manager-primary-action"
            >

                <i class="bi bi-inbox-fill"></i>

                Review Pending Requests

            </a>

        </div>

    </div>


    <div class="manager-hero-icon">

        <i class="bi bi-clipboard2-check-fill"></i>

    </div>

</section>


<!-- KPI CARDS -->

<div class="row g-4 mb-4">

    <div class="col-sm-6 col-xl-3">

        <div class="employee-kpi-card">

            <div class="employee-kpi-top">

                <div class="employee-kpi-icon">

                    <i class="bi bi-people"></i>

                </div>

            </div>

            <span class="employee-kpi-label">
                Active Team
            </span>

            <div class="employee-kpi-value">
                <?= $teamCount ?>
            </div>

            <div class="employee-kpi-footer">
                Employees reporting to you
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

                    <i class="bi bi-hourglass-split"></i>

                </div>

            </div>

            <span class="employee-kpi-label">
                Pending
            </span>

            <div class="employee-kpi-value">

                <?= (int)(
                    $counts[
                        'pending_requests'
                    ]
                    ?? 0
                ) ?>

            </div>

            <div class="employee-kpi-footer">
                Waiting for your decision
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

                    <i class="bi bi-check-circle"></i>

                </div>

            </div>

            <span class="employee-kpi-label">
                Approved
            </span>

            <div class="employee-kpi-value">

                <?= (int)(
                    $counts[
                        'approved_requests'
                    ]
                    ?? 0
                ) ?>

            </div>

            <div class="employee-kpi-footer">
                Approved team requests
            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="employee-kpi-card">

            <div class="employee-kpi-top">

                <div
                    class="employee-kpi-icon
                           manager-rejected-icon"
                >

                    <i class="bi bi-x-circle"></i>

                </div>

            </div>

            <span class="employee-kpi-label">
                Rejected
            </span>

            <div class="employee-kpi-value">

                <?= (int)(
                    $counts[
                        'rejected_requests'
                    ]
                    ?? 0
                ) ?>

            </div>

            <div class="employee-kpi-footer">
                Rejected team requests
            </div>

        </div>

    </div>

</div>


<!-- PENDING REQUESTS -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                NEEDS ATTENTION
            </div>

            <h5>
                Pending Leave Requests
            </h5>

            <small class="text-muted">
                Oldest requests are shown first.
            </small>

        </div>


        <a
            href="/manager/pending-requests.php?status=Pending"
            class="employee-view-all"
        >
            View All

            <i class="bi bi-arrow-right"></i>
        </a>

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
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Duration</th>
                        <th>Submitted</th>
                        <th class="text-end">
                            Action
                        </th>
                    </tr>

                </thead>


                <tbody>

                <?php if (
                    !$pendingRequests
                ): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center py-5"
                        >

                            <div class="employee-empty-state">

                                <div class="employee-empty-icon">

                                    <i
                                        class="bi
                                               bi-check2-circle"
                                    ></i>

                                </div>

                                <h6>
                                    All caught up
                                </h6>

                                <p>
                                    There are no pending
                                    leave requests requiring
                                    your attention.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $pendingRequests
                    as $request
                ): ?>

                    <tr>

                        <td>

                            <div class="employee-cell">

                                <div class="employee-avatar">

                                    <?= escape(
                                        strtoupper(
                                            substr(
                                                $request[
                                                    'first_name'
                                                ],
                                                0,
                                                1
                                            )
                                            .
                                            substr(
                                                $request[
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
                                            $request[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $request[
                                                'last_name'
                                            ]
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= escape(
                                            $request[
                                                'employee_code'
                                            ]
                                        ) ?>

                                    </span>


                                    <small>

                                        <?= escape(
                                            $request[
                                                'job_title'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?= escape(
                                $request[
                                    'leave_type_name'
                                ]
                            ) ?>

                        </td>


                        <td>

                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $request[
                                            'start_date'
                                        ]
                                    )
                                )
                            ) ?>

                            <small
                                class="d-block text-muted"
                            >

                                to

                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $request[
                                                'end_date'
                                            ]
                                        )
                                    )
                                ) ?>

                            </small>

                        </td>


                        <td>

                            <strong>
                                <?= number_format(
                                    (float)$request[
                                        'number_of_days'
                                    ],
                                    2
                                ) ?>
                            </strong>

                            <small class="text-muted">
                                days
                            </small>

                        </td>


                        <td>

                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $request[
                                            'applied_at'
                                        ]
                                    )
                                )
                            ) ?>

                        </td>


                        <td class="text-end">

                            <a
                                href="/manager/view-request.php?id=<?= (int)$request['application_id'] ?>"
                                class="btn
                                       btn-sm
                                       btn-outline-primary"
                            >

                                Review

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
    . '/../includes/manager/footer.php';