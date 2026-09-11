<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


$pageTitle = 'Notifications';


/*
|--------------------------------------------------------------------------
| Employee Profile
|--------------------------------------------------------------------------
*/

$profileStmt =
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


/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$statusFilter =
    $_GET['status']
    ?? 'All';


$allowedFilters = [
    'All',
    'Unread',
    'Read'
];


if (
    !in_array(
        $statusFilter,
        $allowedFilters,
        true
    )
) {
    $statusFilter = 'All';
}


/*
|--------------------------------------------------------------------------
| Counts
|--------------------------------------------------------------------------
*/

$countStmt =
    $pdo->prepare(
        "SELECT
            COUNT(*) AS total_count,

            SUM(
                CASE
                    WHEN is_read = FALSE
                    THEN 1
                    ELSE 0
                END
            ) AS unread_count,

            SUM(
                CASE
                    WHEN is_read = TRUE
                    THEN 1
                    ELSE 0
                END
            ) AS read_count

         FROM notifications

         WHERE user_id =
            :user_id"
    );


$countStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$counts =
    $countStmt->fetch();


/*
|--------------------------------------------------------------------------
| Load Notifications
|--------------------------------------------------------------------------
*/

$sql =
    "SELECT
        notification_id,
        title,
        message,
        is_read,
        created_at

     FROM notifications

     WHERE user_id =
        :user_id";


$params = [
    'user_id' =>
        (int)$_SESSION['user_id']
];


if (
    $statusFilter === 'Unread'
) {

    $sql .=
        " AND is_read = FALSE";

} elseif (
    $statusFilter === 'Read'
) {

    $sql .=
        " AND is_read = TRUE";
}


$sql .=
    " ORDER BY created_at DESC
      LIMIT 100";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);


$notifications =
    $stmt->fetchAll();


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
            Notifications
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
                Notifications
            </h2>

            <p>
                Stay updated about your
                leave application decisions.
            </p>

        </div>


        <?php if (
            (int)(
                $counts[
                    'unread_count'
                ] ?? 0
            ) > 0
        ): ?>

            <form
                method="POST"
                action="/employee/notification-action.php"
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
                    name="action"
                    value="read_all"
                >

                <input
                    type="hidden"
                    name="return_status"
                    value="<?= escape(
                        $statusFilter
                    ) ?>"
                >


                <button
                    type="submit"
                    class="btn-professional-secondary"
                >

                    <i
                        class="bi
                               bi-check2-all"
                    ></i>

                    Mark All as Read

                </button>

            </form>

        <?php endif; ?>

    </div>

</div>


<!-- SUMMARY -->

<div class="row g-3 mb-4">

    <div class="col-sm-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Total
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'total_count'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>

            <div class="mini-summary-icon purple">

                <i class="bi bi-bell"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Unread
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'unread_count'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>

            <div class="mini-summary-icon orange">

                <i
                    class="bi
                           bi-envelope-exclamation"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Read
                </span>

                <strong>
                    <?= (int)(
                        $counts[
                            'read_count'
                        ] ?? 0
                    ) ?>
                </strong>

            </div>

            <div class="mini-summary-icon green">

                <i
                    class="bi
                           bi-envelope-check"
                ></i>

            </div>

        </div>

    </div>

</div>


<!-- FILTERS -->

<div class="admin-card mb-4">

    <div class="admin-card-body">

        <div class="leave-history-filters">

            <?php foreach (
                $allowedFilters
                as $filter
            ): ?>

                <a
                    href="/employee/notifications.php?status=<?= urlencode(
                        $filter
                    ) ?>"
                    class="leave-filter-pill
                    <?= $statusFilter
                        === $filter
                            ? 'active'
                            : ''
                    ?>"
                >

                    <?= escape(
                        $filter
                    ) ?>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

</div>


<!-- NOTIFICATION LIST -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                ACTIVITY
            </div>

            <h5>
                Recent Notifications
            </h5>

            <small class="text-muted">

                <?= count(
                    $notifications
                ) ?>
                notification(s)

            </small>

        </div>


        <a
            href="/employee/leave-history.php"
            class="employee-view-all"
        >

            Leave History

            <i class="bi bi-arrow-right"></i>

        </a>

    </div>


    <div class="admin-card-body">

        <?php if (
            !$notifications
        ): ?>

            <div class="employee-empty-state">

                <div class="employee-empty-icon">

                    <i class="bi bi-bell-slash"></i>

                </div>

                <h6>
                    No notifications
                </h6>

                <p>
                    You do not have any
                    notifications matching this filter.
                </p>

            </div>


        <?php else: ?>

            <div class="notification-list">

                <?php foreach (
                    $notifications
                    as $notification
                ): ?>

                    <?php

                    $titleLower =
                        strtolower(
                            $notification['title']
                        );


                    $notificationTone =
                        'general';

                    $notificationIcon =
                        'bi-bell-fill';


                    if (
                        str_contains(
                            $titleLower,
                            'approved'
                        )
                    ) {

                        $notificationTone =
                            'approved';

                        $notificationIcon =
                            'bi-check-circle-fill';

                    } elseif (
                        str_contains(
                            $titleLower,
                            'rejected'
                        )
                    ) {

                        $notificationTone =
                            'rejected';

                        $notificationIcon =
                            'bi-x-circle-fill';
                    }

                    ?>


                    <article
                        class="notification-card
                        <?= (int)$notification[
                            'is_read'
                        ] === 0
                            ? 'unread'
                            : 'read'
                        ?>"
                    >

                        <div
                            class="notification-icon
                                   notification-<?= escape(
                                       $notificationTone
                                   ) ?>"
                        >

                            <i
                                class="bi
                                <?= escape(
                                    $notificationIcon
                                ) ?>"
                            ></i>

                        </div>


                        <div
                            class="notification-content"
                        >

                            <div
                                class="notification-title-row"
                            >

                                <div>

                                    <h6>

                                        <?= escape(
                                            $notification[
                                                'title'
                                            ]
                                        ) ?>

                                    </h6>


                                    <?php if (
                                        (int)$notification[
                                            'is_read'
                                        ] === 0
                                    ): ?>

                                        <span
                                            class="notification-new-badge"
                                        >
                                            NEW
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <time>

                                    <?= escape(
                                        date(
                                            'd M Y · H:i',
                                            strtotime(
                                                $notification[
                                                    'created_at'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </time>

                            </div>


                            <p>

                                <?= nl2br(
                                    escape(
                                        $notification[
                                            'message'
                                        ]
                                    )
                                ) ?>

                            </p>


                            <div
                                class="notification-actions"
                            >

                                <a
                                    href="/employee/leave-history.php"
                                    class="notification-action-link"
                                >

                                    <i
                                        class="bi
                                               bi-clock-history"
                                    ></i>

                                    View Leave History

                                </a>


                                <form
                                    method="POST"
                                    action="/employee/notification-action.php"
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
                                        name="notification_id"
                                        value="<?= (int)$notification[
                                            'notification_id'
                                        ] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="return_status"
                                        value="<?= escape(
                                            $statusFilter
                                        ) ?>"
                                    >


                                    <?php if (
                                        (int)$notification[
                                            'is_read'
                                        ] === 0
                                    ): ?>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="read"
                                            class="notification-action-button"
                                        >

                                            <i
                                                class="bi
                                                       bi-check2"
                                            ></i>

                                            Mark as Read

                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="unread"
                                            class="notification-action-button"
                                        >

                                            <i
                                                class="bi
                                                       bi-envelope"
                                            ></i>

                                            Mark Unread

                                        </button>

                                    <?php endif; ?>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';