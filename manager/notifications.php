<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$pageTitle = 'Notifications';


/*
|--------------------------------------------------------------------------
| Manager Profile
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


$managerProfile =
    $profileStmt->fetch();


if (!$managerProfile) {

    http_response_code(403);

    exit(
        'Manager profile not found.'
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
| Notifications
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
    . '/../includes/manager/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/manager/dashboard.php">
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
                Review alerts about new
                employee leave requests.
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
                action="/manager/notification-action.php"
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

                    <i class="bi bi-check2-all"></i>

                    Mark All as Read

                </button>

            </form>

        <?php endif; ?>

    </div>

</div>


<div class="row g-3 mb-4">

    <div class="col-sm-4">

        <div class="mini-summary-card">

            <div>
                <span>Total</span>

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
                <span>Unread</span>

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
                <span>Read</span>

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


<div class="admin-card mb-4">

    <div class="admin-card-body">

        <div class="leave-history-filters">

            <?php foreach (
                $allowedFilters
                as $filter
            ): ?>

                <a
                    href="/manager/notifications.php?status=<?= urlencode(
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


<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <div class="employee-section-label">
                ACTIVITY
            </div>

            <h5>
                Manager Notifications
            </h5>

            <small class="text-muted">

                <?= count(
                    $notifications
                ) ?>
                notification(s)

            </small>

        </div>


        <a
            href="/manager/pending-requests.php?status=Pending"
            class="employee-view-all"
        >

            Leave Requests

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
                    There are no notifications
                    matching this filter.
                </p>

            </div>


        <?php else: ?>

            <div class="notification-list">

                <?php foreach (
                    $notifications
                    as $notification
                ): ?>

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
                                   notification-request"
                        >

                            <i
                                class="bi
                                       bi-inbox-fill"
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


                            <div class="notification-actions">

                                <a
                                    href="/manager/pending-requests.php?status=Pending"
                                    class="notification-action-link"
                                >

                                    <i class="bi bi-inbox"></i>

                                    View Requests

                                </a>


                                <form
                                    method="POST"
                                    action="/manager/notification-action.php"
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

                                            <i class="bi bi-check2"></i>

                                            Mark as Read

                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="submit"
                                            name="action"
                                            value="unread"
                                            class="notification-action-button"
                                        >

                                            <i class="bi bi-envelope"></i>

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
    . '/../includes/manager/footer.php';