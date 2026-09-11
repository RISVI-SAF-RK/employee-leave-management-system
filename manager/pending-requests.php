<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$pageTitle = 'Leave Requests';


/*
|--------------------------------------------------------------------------
| Manager
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
| Status Filter
|--------------------------------------------------------------------------
*/

$status =
    $_GET['status']
    ?? 'Pending';


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

    $status = 'Pending';
}


/*
|--------------------------------------------------------------------------
| Requests
|--------------------------------------------------------------------------
*/

$sql =
    "SELECT
        la.application_id,
        la.start_date,
        la.end_date,
        la.number_of_days,
        la.status,
        la.applied_at,

        e.employee_code,
        e.first_name,
        e.last_name,
        e.job_title,

        d.department_name,

        lt.leave_type_name,
        lt.is_paid

     FROM leave_applications la

     INNER JOIN employees e
        ON la.employee_id =
           e.employee_id

     INNER JOIN departments d
        ON e.department_id =
           d.department_id

     INNER JOIN leave_types lt
        ON la.leave_type_id =
           lt.leave_type_id

     WHERE e.manager_id =
        :manager_id";


$params = [
    'manager_id' =>
        $managerId
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
        CASE
            WHEN la.status = 'Pending'
            THEN 0
            ELSE 1
        END,
        la.applied_at DESC";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$requests =
    $stmt->fetchAll();


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
            Leave Requests
        </span>

    </div>


    <h2>
        Team Leave Requests
    </h2>


    <p>
        Review leave applications submitted
        by employees currently assigned to you.
    </p>

</div>


<div class="admin-card mb-4">

    <div class="admin-card-body">

        <div class="leave-history-filters">

            <?php foreach (
                $allowedStatuses
                as $filterStatus
            ): ?>

                <a
                    href="/manager/pending-requests.php?status=<?= urlencode(
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


<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                <?= escape(
                    $status
                ) ?>
                Requests
            </h5>

            <small class="text-muted">

                <?= count(
                    $requests
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
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th class="text-end">
                            Action
                        </th>
                    </tr>

                </thead>


                <tbody>

                <?php if (!$requests): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center py-5"
                        >

                            <div class="employee-empty-state">

                                <div class="employee-empty-icon">

                                    <i
                                        class="bi
                                               bi-inbox"
                                    ></i>

                                </div>

                                <h6>
                                    No requests found
                                </h6>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $requests
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
                                                'department_name'
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

                            <?= number_format(
                                (float)$request[
                                    'number_of_days'
                                ],
                                2
                            ) ?>
                            days

                        </td>


                        <td>

                            <span
                                class="leave-status-badge
                                       leave-status-<?= strtolower(
                                           $request[
                                               'status'
                                           ]
                                       ) ?>"
                            >

                                <?= escape(
                                    $request[
                                        'status'
                                    ]
                                ) ?>

                            </span>

                        </td>


                        <td class="text-end">

                            <a
                                href="/manager/view-request.php?id=<?= (int)$request['application_id'] ?>"
                                class="btn
                                       btn-sm
                                       btn-outline-primary"
                            >

                                <i class="bi bi-eye"></i>

                                <?= $request[
                                    'status'
                                ] === 'Pending'
                                    ? 'Review'
                                    : 'View'
                                ?>

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