<?php

declare(strict_types=1);

require_once __DIR__ . '/../role_check.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../../config/database.php';

requireRole('Manager');

$pageTitle =
    $pageTitle ?? 'Manager Portal';

$flash =
    getFlash();

$notificationCountStmt =
    $pdo->prepare(
        "SELECT COUNT(*)
         FROM notifications
         WHERE user_id = :user_id
           AND is_read = FALSE"
    );


$notificationCountStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$unreadNotificationCount =
    (int)$notificationCountStmt
        ->fetchColumn();

$currentPage =
    basename(
        $_SERVER['PHP_SELF']
    );


$isActive = static function (
    array $pages
) use ($currentPage): string {

    return in_array(
        $currentPage,
        $pages,
        true
    )
        ? 'active'
        : '';
};


/*
|--------------------------------------------------------------------------
| Manager Display Information
|--------------------------------------------------------------------------
*/

$displayName =
    'Manager';


if (
    isset($managerProfile)
    &&
    !empty(
        $managerProfile[
            'first_name'
        ]
    )
) {

    $displayName =
        trim(
            $managerProfile[
                'first_name'
            ]
            . ' '
            . $managerProfile[
                'last_name'
            ]
        );
}


$email =
    $_SESSION['email']
    ?? '';


$avatarLetter =
    strtoupper(
        substr(
            $displayName,
            0,
            1
        )
    );
?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= escape($pageTitle) ?> | ELMS
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>


<body class="admin-body manager-portal-body">


<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<aside
    class="admin-sidebar manager-sidebar"
    id="adminSidebar"
>

    <div class="sidebar-header">

        <a
            href="/manager/dashboard.php"
            class="brand"
        >

            <div class="brand-icon">

                <i
                    class="bi
                           bi-calendar2-check-fill"
                ></i>

            </div>


            <div class="brand-text">

                <span class="brand-title">
                    ELMS
                </span>

                <span class="brand-subtitle">
                    Manager Portal
                </span>

            </div>

        </a>


        <button
            type="button"
            class="sidebar-close"
            id="sidebarClose"
            aria-label="Close navigation"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>


    <div class="sidebar-user">

        <div class="sidebar-avatar">

            <?= escape(
                $avatarLetter
            ) ?>

        </div>


        <div class="sidebar-user-details">

            <span class="sidebar-user-role">

                <?= escape(
                    $displayName
                ) ?>

            </span>


            <span class="sidebar-user-email">

                <?= escape(
                    $email
                ) ?>

            </span>

        </div>

    </div>


    <nav class="sidebar-nav">

        <div class="nav-section-title">
            Main
        </div>


        <a
            href="/manager/dashboard.php"
            class="sidebar-link
            <?= $isActive([
                'dashboard.php'
            ]) ?>"
        >

            <i
                class="bi
                       bi-grid-1x2-fill"
            ></i>

            <span>
                Dashboard
            </span>

        </a>


        <div class="nav-section-title">
            Leave Management
        </div>


        <a
            href="/manager/pending-requests.php"
            class="sidebar-link
            <?= $isActive([
                'pending-requests.php',
                'view-request.php'
            ]) ?>"
        >

            <i
                class="bi
                       bi-inbox-fill"
            ></i>

            <span>
                Leave Requests
            </span>

        </a>


        <div class="nav-section-title">
            Team
        </div>


        <a
    href="/manager/my-team.php"
    class="sidebar-link
    <?= $isActive([
        'my-team.php',
        'view-team-member.php'
    ]) ?>"
>
    <i class="bi bi-people-fill"></i>

    <span>
        My Team
    </span>
</a>


        <a
    href="/manager/team-reports.php"
    class="sidebar-link
    <?= $isActive([
        'team-reports.php'
    ]) ?>"
>
    <i class="bi bi-bar-chart-fill"></i>

    <span>
        Team Reports
    </span>
</a>


        <div class="nav-section-title">
            Account
        </div>


        <a
    href="/manager/notifications.php"
    class="sidebar-link
    <?= $isActive([
        'notifications.php'
    ]) ?>"
>
    <i class="bi bi-bell-fill"></i>

    <span>
        Notifications
    </span>

    <?php if (
        $unreadNotificationCount > 0
    ): ?>

        <span
            class="sidebar-notification-count"
        >
            <?= $unreadNotificationCount > 99
                ? '99+'
                : $unreadNotificationCount
            ?>
        </span>

    <?php endif; ?>
</a>  

    </nav>


    <div class="sidebar-footer">

        <a
            href="/logout.php"
            class="logout-link"
        >

            <i
                class="bi
                       bi-box-arrow-right"
            ></i>

            <span>
                Sign Out
            </span>

        </a>

    </div>

</aside>


<div class="admin-main">


    <header class="admin-topbar">

        <div class="topbar-left">

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Open navigation"
            >

                <i class="bi bi-list"></i>

            </button>


            <div>

                <div class="topbar-label">
                    Employee Leave Management System
                </div>


                <h1 class="topbar-title">

                    <?= escape(
                        $pageTitle
                    ) ?>

                </h1>

            </div>

        </div>


        <div class="topbar-right">

         <a
    href="/manager/notifications.php"
    class="topbar-icon-button
           topbar-notification-button"
    aria-label="Notifications"
>
    <i class="bi bi-bell"></i>

    <?php if (
        $unreadNotificationCount > 0
    ): ?>

        <span
            class="topbar-notification-badge"
        >
            <?= $unreadNotificationCount > 99
                ? '99+'
                : $unreadNotificationCount
            ?>
        </span>

    <?php endif; ?>
</a>   


            <div class="topbar-profile">

                <div class="topbar-avatar">

                    <?= escape(
                        $avatarLetter
                    ) ?>

                </div>


                <div class="topbar-profile-text">

                    <strong>
                        <?= escape(
                            $displayName
                        ) ?>
                    </strong>

                    <span>
                        Manager
                    </span>

                </div>

            </div>

        </div>

    </header>


    <main class="admin-content">

        <?php if ($flash): ?>

            <div
                class="alert
                       alert-<?= escape(
                           $flash['type']
                       ) ?>
                       alert-dismissible
                       fade
                       show
                       shadow-sm"
            >

                <?= escape(
                    $flash['message']
                ) ?>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>