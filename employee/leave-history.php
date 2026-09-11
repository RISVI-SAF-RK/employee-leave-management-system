<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


$pageTitle = 'Leave History';


/*
|--------------------------------------------------------------------------
| Employee
|--------------------------------------------------------------------------
*/

$profileStmt =
    $pdo->prepare(
        "SELECT
            employee_id,
            employee_code,
            first_name,
            last_name

         FROM employees

         WHERE user_id =
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
| Status Filter
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Applications
|--------------------------------------------------------------------------
*/

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

        lt.leave_type_name,
        lt.is_paid,

        lap.decision,
        lap.comment,
        lap.decision_date

     FROM leave_applications la

     INNER JOIN leave_types lt
        ON la.leave_type_id =
           lt.leave_type_id

     LEFT JOIN leave_approvals lap
        ON la.application_id =
           lap.application_id

     WHERE
        la.employee_id =
            :employee_id";


$params = [
    'employee_id' =>
        $employeeId
];


if ($status !== 'All') {

    $sql .=
        " AND la.status =
            :status";

    $params['status'] =
        $status;
}


$sql .=
    " ORDER BY
        la.applied_at DESC";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$applications =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Counters
|--------------------------------------------------------------------------
*/

$countStmt =
    $pdo->prepare(
        "SELECT
            COUNT(*) AS total,

            SUM(
                status = 'Pending'
            ) AS pending_count,

            SUM(
                status = 'Approved'
            ) AS approved_count,

            SUM(
                status = 'Rejected'
            ) AS rejected_count

         FROM leave_applications

         WHERE employee_id =
            :employee_id"
    );


$countStmt->execute([
    'employee_id' =>
        $employeeId
]);


$counts =
    $countStmt->fetch();


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
            Leave History
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
                Leave History
            </h2>

            <p>
                Track your submitted,
                approved and rejected
                leave requests.
            </p>

        </div>


        <a
            href="/employee/apply-leave.php"
            class="btn-professional-primary"
        >

            <i
                class="bi
                       bi-calendar-plus-fill"
            ></i>

            Apply for Leave

        </a>

    </div>

</div>


<!-- SUMMARY -->

<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">

        <div class="mini-summary-card">

            <div>
                <span>Total</span>

                <strong>
                    <?= (int)(
                        $counts['total']
                        ?? 0
                    ) ?>
                </strong>
            </div>

            <div class="mini-summary-icon purple">
                <i class="bi bi-files"></i>
            </div>

        </div>

    </div>


    <div class="col-6 col-lg-3">

        <div class="mini-summary-card">

            <div>
                <span>Pending</span>

                <strong>
                    <?= (int)(
                        $counts[
                            'pending_count'
                        ]
                        ?? 0
                    ) ?>
                </strong>
            </div>

            <div class="mini-summary-icon orange">
                <i class="bi bi-hourglass-split"></i>
            </div>

        </div>

    </div>


    <div class="col-6 col-lg-3">

        <div class="mini-summary-card">

            <div>
                <span>Approved</span>

                <strong>
                    <?= (int)(
                        $counts[
                            'approved_count'
                        ]
                        ?? 0
                    ) ?>
                </strong>
            </div>

            <div class="mini-summary-icon green">
                <i class="bi bi-check-circle"></i>
            </div>

        </div>

    </div>


    <div class="col-6 col-lg-3">

        <div class="mini-summary-card">

            <div>
                <span>Rejected</span>

                <strong>
                    <?= (int)(
                        $counts[
                            'rejected_count'
                        ]
                        ?? 0
                    ) ?>
                </strong>
            </div>

            <div
                class="leave-summary-red"
            >

                <i class="bi bi-x-circle"></i>

            </div>

        </div>

    </div>

</div>


<!-- FILTER -->

<div class="admin-card mb-4">

    <div class="admin-card-body">

        <div
            class="leave-history-filters"
        >

            <?php foreach (
                $allowedStatuses
                as $filterStatus
            ): ?>

                <a
                    href="/employee/leave-history.php?status=<?= urlencode(
                        $filterStatus
                    ) ?>"
                    class="leave-filter-pill
                    <?= $status
                        === $filterStatus
                            ? 'active'
                            : ''
                    ?>"
                >

                    <?= escape(
                        $filterStatus
                    ) ?>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

</div>


<!-- HISTORY -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                Leave Applications
            </h5>

            <small class="text-muted">
                <?= count(
                    $applications
                ) ?>
                result(s)
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
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Duration</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th class="text-end">
                            Action
                        </th>
                    </tr>

                </thead>


                <tbody>

                <?php if (
                    !$applications
                ): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center
                                   py-5"
                        >

                            <div
                                class="employee-empty-state"
                            >

                                <div
                                    class="employee-empty-icon"
                                >

                                    <i
                                        class="bi
                                               bi-calendar-x"
                                    ></i>

                                </div>


                                <h6>
                                    No leave applications
                                </h6>


                                <p>
                                    No applications match
                                    the selected status.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $applications
                    as $application
                ): ?>

                    <tr>

                        <td>

                            <div
                                class="leave-history-type"
                            >

                                <div>

                                    <i
                                        class="bi
                                               bi-calendar-event"
                                    ></i>

                                </div>


                                <span>

                                    <strong>
                                        <?= escape(
                                            $application[
                                                'leave_type_name'
                                            ]
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= (int)$application[
                                            'is_paid'
                                        ] === 1
                                            ? 'Paid Leave'
                                            : 'Unpaid Leave'
                                        ?>
                                    </small>

                                </span>

                            </div>

                        </td>


                        <td>

                            <strong
                                class="leave-history-date"
                            >

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

                            </strong>

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

                            <strong>
                                <?= number_format(
                                    (float)$application[
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
                                        $application[
                                            'applied_at'
                                        ]
                                    )
                                )
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

                                <?php if (
                                    $application[
                                        'status'
                                    ]
                                    === 'Pending'
                                ): ?>

                                    <i
                                        class="bi
                                               bi-hourglass-split"
                                    ></i>

                                <?php elseif (
                                    $application[
                                        'status'
                                    ]
                                    === 'Approved'
                                ): ?>

                                    <i
                                        class="bi
                                               bi-check-circle-fill"
                                    ></i>

                                <?php else: ?>

                                    <i
                                        class="bi
                                               bi-x-circle-fill"
                                    ></i>

                                <?php endif; ?>


                                <?= escape(
                                    $application[
                                        'status'
                                    ]
                                ) ?>

                            </span>

                        </td>


                        <td class="text-end">

                            <a
                                href="/employee/view-leave.php?id=<?= (int)$application['application_id'] ?>"
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
    . '/../includes/employee/footer.php';