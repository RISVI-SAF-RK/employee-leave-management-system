<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$pageTitle = 'My Team';

$currentYear = (int)date('Y');


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
| Filters
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search']
        ?? ''
    );


$status =
    $_GET['status']
    ?? 'All';


$allowedStatuses = [
    'All',
    'Active',
    'Inactive'
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
| Build Team Query
|--------------------------------------------------------------------------
*/

$where = [
    "e.manager_id =
        :manager_id"
];


$params = [
    'manager_id' =>
        $managerId,

    'balance_year' =>
        $currentYear
];


if ($status !== 'All') {

    $where[] =
        "e.status =
            :employee_status";

    $params[
        'employee_status'
    ] =
        $status;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| CONCAT_WS allows us to use only one :search placeholder.
| This avoids the PDO placeholder issue we fixed earlier.
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] =
        "CONCAT_WS(
            ' ',
            e.employee_code,
            e.first_name,
            e.last_name,
            u.email,
            e.job_title,
            d.department_name
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
| Load Direct Reports
|--------------------------------------------------------------------------
*/

$sql =
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

        d.department_name,

        COALESCE(
            (
                SELECT
                    SUM(
                        lb.remaining_days
                    )

                FROM leave_balances lb

                WHERE
                    lb.employee_id =
                        e.employee_id

                    AND lb.balance_year =
                        :balance_year
            ),
            0
        ) AS total_remaining_days,

        (
            SELECT COUNT(*)

            FROM leave_applications la

            WHERE
                la.employee_id =
                    e.employee_id

                AND la.status =
                    'Pending'
        ) AS pending_requests

     FROM employees e

     INNER JOIN users u
        ON e.user_id =
           u.user_id

     INNER JOIN departments d
        ON e.department_id =
           d.department_id

     WHERE "
    . $whereSql
    . "

     ORDER BY
        CASE
            WHEN e.status = 'Active'
            THEN 0
            ELSE 1
        END,
        e.first_name,
        e.last_name";


$stmt =
    $pdo->prepare(
        $sql
    );


$stmt->execute(
    $params
);


$teamMembers =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

$totalTeamStmt =
    $pdo->prepare(
        "SELECT
            COUNT(*) AS total_team,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'Active'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS active_team,

            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'Inactive'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS inactive_team

         FROM employees

         WHERE manager_id =
            :manager_id"
    );


$totalTeamStmt->execute([
    'manager_id' =>
        $managerId
]);


$teamSummary =
    $totalTeamStmt->fetch();


$pendingStmt =
    $pdo->prepare(
        "SELECT COUNT(*)

         FROM leave_applications la

         INNER JOIN employees e
            ON la.employee_id =
               e.employee_id

         WHERE
            e.manager_id =
                :manager_id

            AND la.status =
                'Pending'"
    );


$pendingStmt->execute([
    'manager_id' =>
        $managerId
]);


$totalPendingRequests =
    (int)$pendingStmt
        ->fetchColumn();


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
            My Team
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
                My Team
            </h2>

            <p>
                View employees assigned to you,
                monitor their leave balances
                and review recent leave activity.
            </p>

        </div>


        <a
            href="/manager/team-reports.php"
            class="btn-professional-secondary"
        >

            <i class="bi bi-bar-chart-fill"></i>

            Team Reports

        </a>

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
                    Team Members
                </span>

                <strong>

                    <?= (int)(
                        $teamSummary[
                            'total_team'
                        ]
                        ?? 0
                    ) ?>

                </strong>

            </div>


            <div
                class="mini-summary-icon
                       purple"
            >

                <i class="bi bi-people-fill"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Active
                </span>

                <strong>

                    <?= (int)(
                        $teamSummary[
                            'active_team'
                        ]
                        ?? 0
                    ) ?>

                </strong>

            </div>


            <div
                class="mini-summary-icon
                       green"
            >

                <i
                    class="bi
                           bi-person-check-fill"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Inactive
                </span>

                <strong>

                    <?= (int)(
                        $teamSummary[
                            'inactive_team'
                        ]
                        ?? 0
                    ) ?>

                </strong>

            </div>


            <div
                class="leave-summary-red"
            >

                <i
                    class="bi
                           bi-person-x-fill"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Pending Requests
                </span>

                <strong>
                    <?= $totalPendingRequests ?>
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

</div>


<!-- =========================================================
     SEARCH / FILTER
     ========================================================= -->

<div class="admin-card mb-4">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                TEAM DIRECTORY
            </div>

            <h5>
                Search Team Members
            </h5>

            <small class="text-muted">
                Search by employee code,
                name, email, role or department.
            </small>

        </div>


        <div class="header-icon-box">

            <i class="bi bi-search"></i>

        </div>

    </div>


    <div class="admin-card-body">

        <form
            method="GET"
            class="row
                   g-3
                   align-items-end"
        >

            <div class="col-lg-8">

                <label
                    for="search"
                    class="professional-form-label"
                >
                    Search
                </label>


                <div class="admin-search-input">

                    <i class="bi bi-search"></i>


                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control
                               professional-input"
                        placeholder="Employee code, name, email, job title..."
                        value="<?= escape(
                            $search
                        ) ?>"
                    >

                </div>

            </div>


            <div class="col-lg-2">

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


            <div class="col-lg-2">

                <button
                    type="submit"
                    class="btn-professional-primary
                           w-100"
                >

                    <i class="bi bi-funnel"></i>

                    Apply

                </button>

            </div>


            <?php if (
                $search !== ''
                ||
                $status !== 'All'
            ): ?>

                <div class="col-12">

                    <a
                        href="/manager/my-team.php"
                        class="employee-text-link"
                    >

                        <i
                            class="bi
                                   bi-arrow-counterclockwise"
                        ></i>

                        Clear Filters

                    </a>

                </div>

            <?php endif; ?>

        </form>

    </div>

</div>


<!-- =========================================================
     TEAM TABLE
     ========================================================= -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                Team Members
            </h5>

            <small class="text-muted">

                <?= count(
                    $teamMembers
                ) ?>

                employee(s) shown

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
                        Contact
                    </th>

                    <th>
                        Position
                    </th>

                    <th>
                        <?= $currentYear ?>
                        Remaining
                    </th>

                    <th>
                        Pending
                    </th>

                    <th>
                        Status
                    </th>

                    <th class="text-end">
                        Action
                    </th>

                </tr>

                </thead>


                <tbody>

                <?php if (
                    !$teamMembers
                ): ?>

                    <tr>

                        <td
                            colspan="7"
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
                                               bi-people"
                                    ></i>

                                </div>


                                <h6>
                                    No team members found
                                </h6>


                                <p>
                                    No employees match the
                                    selected search and
                                    status filters.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $teamMembers
                    as $member
                ): ?>

                    <tr>

                        <!-- Employee -->

                        <td>

                            <div class="employee-cell">

                                <div
                                    class="employee-avatar
                                           team-member-avatar"
                                >

                                    <?= escape(
                                        strtoupper(
                                            substr(
                                                $member[
                                                    'first_name'
                                                ],
                                                0,
                                                1
                                            )
                                            .
                                            substr(
                                                $member[
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
                                            $member[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $member[
                                                'last_name'
                                            ]
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= escape(
                                            $member[
                                                'employee_code'
                                            ]
                                        ) ?>

                                    </span>


                                    <small>

                                        <?= escape(
                                            $member[
                                                'department_name'
                                            ]
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>


                        <!-- Contact -->

                        <td>

                            <div
                                class="team-contact-cell"
                            >

                                <span>

                                    <i
                                        class="bi
                                               bi-envelope"
                                    ></i>

                                    <?= escape(
                                        $member[
                                            'email'
                                        ]
                                    ) ?>

                                </span>


                                <?php if (
                                    !empty(
                                        $member[
                                            'phone'
                                        ]
                                    )
                                ): ?>

                                    <small>

                                        <i
                                            class="bi
                                                   bi-telephone"
                                        ></i>

                                        <?= escape(
                                            $member[
                                                'phone'
                                            ]
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </div>

                        </td>


                        <!-- Position -->

                        <td>

                            <strong
                                class="team-position"
                            >

                                <?= escape(
                                    $member[
                                        'job_title'
                                    ]
                                ) ?>

                            </strong>


                            <small
                                class="d-block
                                       text-muted"
                            >

                                Joined

                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $member[
                                                'date_joined'
                                            ]
                                        )
                                    )
                                ) ?>

                            </small>

                        </td>


                        <!-- Remaining -->

                        <td>

                            <span
                                class="team-balance-pill"
                            >

                                <?= number_format(
                                    (float)$member[
                                        'total_remaining_days'
                                    ],
                                    2
                                ) ?>

                                days

                            </span>

                        </td>


                        <!-- Pending -->

                        <td>

                            <?php if (
                                (int)$member[
                                    'pending_requests'
                                ] > 0
                            ): ?>

                                <span
                                    class="leave-status-badge
                                           leave-status-pending"
                                >

                                    <?= (int)$member[
                                        'pending_requests'
                                    ] ?>

                                    pending

                                </span>

                            <?php else: ?>

                                <span
                                    class="team-none-badge"
                                >
                                    None
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Status -->

                        <td>

                            <?php if (
                                $member[
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

                                    Active

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

                                    Inactive

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Action -->

                        <td class="text-end">

                            <a
                                href="/manager/view-team-member.php?id=<?= (int)$member['employee_id'] ?>"
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


<?php

require_once __DIR__
    . '/../includes/manager/footer.php';