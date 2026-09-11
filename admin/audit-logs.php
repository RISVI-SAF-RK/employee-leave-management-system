<?php

declare(strict_types=1);

require_once __DIR__ .
    '/../includes/role_check.php';

require_once __DIR__ .
    '/../includes/functions.php';

require_once __DIR__ .
    '/../config/database.php';


requireRole('Administrator');


$pageTitle = 'Audit Logs';

$error = '';


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$actionFilter =
    trim(
        $_GET['action']
        ?? ''
    );


$search =
    trim(
        $_GET['search']
        ?? ''
    );


$dateFilter =
    trim(
        $_GET['date']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| Validate Date Filter
|--------------------------------------------------------------------------
*/

if ($dateFilter !== '') {

    $dateObject =
        DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $dateFilter
        );


    if (
        !$dateObject
        ||
        $dateObject->format('Y-m-d')
        !== $dateFilter
    ) {

        $dateFilter = '';
    }
}


/*
|--------------------------------------------------------------------------
| Available Audit Actions
|--------------------------------------------------------------------------
*/

try {

    $actionStmt =
        $pdo->query(
            "SELECT DISTINCT action
             FROM audit_logs
             ORDER BY action"
        );


    $availableActions =
        $actionStmt->fetchAll(
            PDO::FETCH_COLUMN
        );


} catch (Throwable $e) {

    error_log(
        'Audit action list error: '
        . $e->getMessage()
    );


    $availableActions = [];
}


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

try {

    $totalLogs =
        (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM audit_logs"
            )
            ->fetchColumn();


    $todayLogs =
        (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM audit_logs
                 WHERE DATE(created_at) =
                    CURDATE()"
            )
            ->fetchColumn();


} catch (Throwable $e) {

    error_log(
        'Audit summary error: '
        . $e->getMessage()
    );


    $totalLogs = 0;
    $todayLogs = 0;
}


/*
|--------------------------------------------------------------------------
| Build Filtered Audit Query
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


if ($actionFilter !== '') {

    $where[] =
        'al.action = :action';

    $params['action'] =
        $actionFilter;
}


if ($dateFilter !== '') {

    $where[] =
        'DATE(al.created_at) = :audit_date';

    $params['audit_date'] =
        $dateFilter;
}


if ($search !== '') {

    /*
    |--------------------------------------------------------------------------
    | One Named Placeholder Only
    |--------------------------------------------------------------------------
    |
    | PDO native prepared statements are enabled in this project.
    | Using one CONCAT_WS search expression avoids repeated named placeholders.
    |--------------------------------------------------------------------------
    */

    $where[] =
        "CONCAT_WS(
            ' ',
            COALESCE(u.email, ''),
            COALESCE(e.employee_code, ''),
            COALESCE(e.first_name, ''),
            COALESCE(e.last_name, ''),
            al.action,
            COALESCE(al.entity_type, ''),
            COALESCE(al.description, ''),
            COALESCE(al.ip_address, '')
        ) LIKE :search";

    $params['search'] =
        '%' . $search . '%';
}


$whereSql =
    $where
        ? 'WHERE ' . implode(
            ' AND ',
            $where
        )
        : '';


/*
|--------------------------------------------------------------------------
| Load Audit Logs
|--------------------------------------------------------------------------
*/

try {

    $auditStmt =
        $pdo->prepare(
            "SELECT
                al.audit_id,
                al.user_id,
                al.action,
                al.entity_type,
                al.entity_id,
                al.description,
                al.ip_address,
                al.user_agent,
                al.created_at,

                u.email,

                r.role_name,

                e.employee_code,
                e.first_name,
                e.last_name

             FROM audit_logs al

             LEFT JOIN users u
                ON al.user_id =
                   u.user_id

             LEFT JOIN roles r
                ON u.role_id =
                   r.role_id

             LEFT JOIN employees e
                ON u.user_id =
                   e.user_id

             $whereSql

             ORDER BY
                al.created_at DESC,
                al.audit_id DESC

             LIMIT 300"
        );


    $auditStmt->execute(
        $params
    );


    $auditLogs =
        $auditStmt->fetchAll();


} catch (Throwable $e) {

    error_log(
        'Audit log list error: '
        . $e->getMessage()
    );


    $auditLogs = [];

    $error =
        'Unable to load audit logs. Please try again.';
}


$displayedLogs =
    count(
        $auditLogs
    );


require_once __DIR__ .
    '/../includes/admin/header.php';

?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/admin/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Audit Logs
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
                Audit Logs
            </h2>

            <p>
                Review important activity recorded
                across the Employee Leave Management System.
            </p>

        </div>


        <div>

            <span
                class="badge
                       text-bg-dark
                       rounded-pill
                       px-3
                       py-2"
            >
                Latest 300 records
            </span>

        </div>

    </div>

</div>


<?php if ($error !== ''): ?>

    <div
        class="alert
               alert-danger
               d-flex
               align-items-center
               gap-2"
    >

        <i
            class="bi
                   bi-exclamation-triangle-fill"
        ></i>

        <div>
            <?= escape($error) ?>
        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Total Audit Records
                </span>

                <strong>
                    <?= number_format(
                        $totalLogs
                    ) ?>
                </strong>

            </div>


            <div class="mini-summary-icon purple">

                <i
                    class="bi
                           bi-journal-text"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Recorded Today
                </span>

                <strong>
                    <?= number_format(
                        $todayLogs
                    ) ?>
                </strong>

            </div>


            <div class="mini-summary-icon blue">

                <i
                    class="bi
                           bi-calendar-check"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Displayed Results
                </span>

                <strong>
                    <?= number_format(
                        $displayedLogs
                    ) ?>
                </strong>

            </div>


            <div class="mini-summary-icon green">

                <i
                    class="bi
                           bi-funnel"
                ></i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     FILTERS
     ========================================================= -->

<div class="admin-card mb-4">

    <div class="admin-card-header">

        <div>

            <h5>
                Filter Audit Activity
            </h5>

            <small class="text-muted">
                Search by user, employee code,
                action, entity, description or IP address.
            </small>

        </div>


        <div class="header-icon-box">

            <i
                class="bi
                       bi-funnel-fill"
            ></i>

        </div>

    </div>


    <div class="admin-card-body">

        <form
            method="GET"
            action="/admin/audit-logs.php"
        >

            <div class="row g-3">

                <div class="col-lg-5">

                    <label
                        for="search"
                        class="professional-form-label"
                    >
                        Search
                    </label>


                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control
                               professional-input"
                        maxlength="150"
                        placeholder="Email, employee, action, IP..."
                        value="<?= escape(
                            $search
                        ) ?>"
                    >

                </div>


                <div class="col-md-6 col-lg-4">

                    <label
                        for="action"
                        class="professional-form-label"
                    >
                        Action
                    </label>


                    <select
                        id="action"
                        name="action"
                        class="form-select
                               professional-input"
                    >

                        <option value="">
                            All Actions
                        </option>


                        <?php foreach (
                            $availableActions
                            as $availableAction
                        ): ?>

                            <option
                                value="<?= escape(
                                    (string)$availableAction
                                ) ?>"
                                <?= $actionFilter
                                    === (string)$availableAction
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= escape(
                                    (string)$availableAction
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-6 col-lg-3">

                    <label
                        for="date"
                        class="professional-form-label"
                    >
                        Date
                    </label>


                    <input
                        type="date"
                        id="date"
                        name="date"
                        class="form-control
                               professional-input"
                        value="<?= escape(
                            $dateFilter
                        ) ?>"
                    >

                </div>

            </div>


            <div class="form-actions mt-4">

                <a
                    href="/admin/audit-logs.php"
                    class="btn-professional-secondary"
                >

                    <i
                        class="bi
                               bi-arrow-counterclockwise"
                    ></i>

                    Clear

                </a>


                <button
                    type="submit"
                    class="btn-professional-primary"
                >

                    <i
                        class="bi
                               bi-search"
                    ></i>

                    Apply Filters

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     AUDIT LOG TABLE
     ========================================================= -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                System Activity
            </h5>

            <small class="text-muted">
                Audit records are read-only.
            </small>

        </div>


        <div class="header-icon-box">

            <i
                class="bi
                       bi-shield-check"
            ></i>

        </div>

    </div>


    <div class="admin-card-body p-0">

        <div class="table-responsive">

            <table
                class="table
                       table-hover
                       align-middle
                       mb-0"
            >

                <thead>

                    <tr>

                        <th>
                            Date & Time
                        </th>

                        <th>
                            User
                        </th>

                        <th>
                            Action
                        </th>

                        <th>
                            Entity
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            IP Address
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$auditLogs): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center
                                   py-5"
                        >

                            <div class="empty-state">

                                <i
                                    class="bi
                                           bi-journal-x"
                                ></i>

                                <h6>
                                    No audit records found
                                </h6>

                                <p>
                                    Try changing the current
                                    search or filter options.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $auditLogs
                    as $log
                ): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $log[
                                                'created_at'
                                            ]
                                        )
                                    )
                                ) ?>
                            </strong>

                            <div
                                class="text-muted
                                       small"
                            >
                                <?= escape(
                                    date(
                                        'h:i:s A',
                                        strtotime(
                                            $log[
                                                'created_at'
                                            ]
                                        )
                                    )
                                ) ?>
                            </div>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $log['user_id']
                                )
                            ): ?>

                                <strong>

                                    <?= escape(
                                        trim(
                                            (
                                                $log[
                                                    'first_name'
                                                ] ?? ''
                                            )
                                            . ' '
                                            . (
                                                $log[
                                                    'last_name'
                                                ] ?? ''
                                            )
                                        )
                                        !== ''
                                            ? trim(
                                                (
                                                    $log[
                                                        'first_name'
                                                    ] ?? ''
                                                )
                                                . ' '
                                                . (
                                                    $log[
                                                        'last_name'
                                                    ] ?? ''
                                                )
                                            )
                                            : (
                                                $log[
                                                    'email'
                                                ]
                                                ?? 'User'
                                            )
                                    ) ?>

                                </strong>


                                <?php if (
                                    !empty(
                                        $log[
                                            'employee_code'
                                        ]
                                    )
                                ): ?>

                                    <div
                                        class="text-muted
                                               small"
                                    >
                                        <?= escape(
                                            $log[
                                                'employee_code'
                                            ]
                                        ) ?>
                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $log[
                                            'role_name'
                                        ]
                                    )
                                ): ?>

                                    <div
                                        class="text-muted
                                               small"
                                    >
                                        <?= escape(
                                            $log[
                                                'role_name'
                                            ]
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                <span
                                    class="text-muted"
                                >
                                    System / Unknown
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span
                                class="badge
                                       text-bg-light
                                       border
                                       text-dark"
                            >
                                <?= escape(
                                    $log['action']
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $log[
                                        'entity_type'
                                    ]
                                )
                            ): ?>

                                <strong>
                                    <?= escape(
                                        $log[
                                            'entity_type'
                                        ]
                                    ) ?>
                                </strong>


                                <?php if (
                                    !empty(
                                        $log[
                                            'entity_id'
                                        ]
                                    )
                                ): ?>

                                    <div
                                        class="text-muted
                                               small"
                                    >
                                        #<?= (int)$log[
                                            'entity_id'
                                        ] ?>
                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                <span
                                    class="text-muted"
                                >
                                    —
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $log[
                                        'description'
                                    ]
                                )
                            ): ?>

                                <?= escape(
                                    $log[
                                        'description'
                                    ]
                                ) ?>

                            <?php else: ?>

                                <span
                                    class="text-muted"
                                >
                                    —
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $log[
                                        'ip_address'
                                    ]
                                )
                            ): ?>

                                <code>
                                    <?= escape(
                                        $log[
                                            'ip_address'
                                        ]
                                    ) ?>
                                </code>

                            <?php else: ?>

                                <span
                                    class="text-muted"
                                >
                                    —
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<div
    class="admin-info-box
           mt-4"
>

    <div class="admin-info-icon">

        <i
            class="bi
                   bi-info-circle-fill"
        ></i>

    </div>


    <div>

        <strong>
            Audit Log Security
        </strong>

        <p>
            This page is read-only and available only
            to Administrators. Audit records should not
            be edited or deleted through the ELMS interface.
        </p>

    </div>

</div>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';
