<?php

declare(strict_types=1);

require_once __DIR__ . '/../role_check.php';
require_once __DIR__ . '/../functions.php';

requireRole('Administrator');

$pageTitle = $pageTitle ?? 'Administrator';

$flash = getFlash();

$currentPage = basename(
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

$email = $_SESSION['email'] ?? 'Administrator';

$avatarLetter = strtoupper(
    substr($email, 0, 1)
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

<body class="admin-body">

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<aside
    class="admin-sidebar"
    id="adminSidebar"
>

    <div class="sidebar-header">

        <a
            href="/admin/dashboard.php"
            class="brand"
        >

            <div class="brand-icon">
                <i class="bi bi-calendar2-check-fill"></i>
            </div>

            <div class="brand-text">

                <span class="brand-title">
                    ELMS
                </span>

                <span class="brand-subtitle">
                    Administration
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
            <?= escape($avatarLetter) ?>
        </div>

        <div class="sidebar-user-details">

            <span class="sidebar-user-role">
                Administrator
            </span>

            <span class="sidebar-user-email">
                <?= escape($email) ?>
            </span>

        </div>

    </div>


    <nav class="sidebar-nav">

        <div class="nav-section-title">
            Main
        </div>

        <a
            href="/admin/dashboard.php"
            class="sidebar-link
            <?= $isActive(['dashboard.php']) ?>"
        >

            <i class="bi bi-grid-1x2-fill"></i>

            <span>
                Dashboard
            </span>

        </a>


        <div class="nav-section-title">
            Organization
        </div>

        <a
            href="/admin/departments.php"
            class="sidebar-link
            <?= $isActive([
                'departments.php',
                'add-department.php',
                'edit-department.php'
            ]) ?>"
        >

            <i class="bi bi-diagram-3-fill"></i>

            <span>
                Departments
            </span>

        </a>

        <a
    href="/admin/employees.php"
    class="sidebar-link
    <?= $isActive([
        'employees.php',
        'add-employee.php',
        'edit-employee.php'
    ]) ?>"
>
    <i class="bi bi-people-fill"></i>

    <span>
        Employees
    </span>
</a>

        <div class="nav-section-title">
            Leave Management
        </div>

        <a
    href="/admin/leave-types.php"
    class="sidebar-link
    <?= $isActive([
        'leave-types.php',
        'add-leave-type.php',
        'edit-leave-type.php'
    ]) ?>"
>
    <i class="bi bi-calendar-event-fill"></i>

    <span>
        Leave Types
    </span>
</a>

        <a
    href="/admin/leave-policies.php"
    class="sidebar-link
    <?= $isActive([
        'leave-policies.php',
        'configure-leave-policy.php'
    ]) ?>"
>
    <i class="bi bi-file-earmark-text-fill"></i>

    <span>
        Leave Policies
    </span>
</a>

        <a
            href="#"
            class="sidebar-link sidebar-link-disabled"
        >

            <i class="bi bi-pie-chart-fill"></i>

            <span>
                Leave Balances
            </span>

        </a>

        <a
            href="#"
            class="sidebar-link sidebar-link-disabled"
        >

            <i class="bi bi-journal-check"></i>

            <span>
                Leave Records
            </span>

        </a>


        <div class="nav-section-title">
            Analytics
        </div>

        <a
            href="#"
            class="sidebar-link sidebar-link-disabled"
        >

            <i class="bi bi-bar-chart-fill"></i>

            <span>
                Reports
            </span>

        </a>

    </nav>


    <div class="sidebar-footer">

        <a
            href="/logout.php"
            class="logout-link"
        >

            <i class="bi bi-box-arrow-right"></i>

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
                    <?= escape($pageTitle) ?>
                </h1>

            </div>

        </div>


        <div class="topbar-right">

            <button
                type="button"
                class="topbar-icon-button"
                aria-label="Notifications"
            >
                <i class="bi bi-bell"></i>
            </button>

            <div class="topbar-profile">

                <div class="topbar-avatar">
                    <?= escape($avatarLetter) ?>
                </div>

                <div class="topbar-profile-text">

                    <strong>
                        Administrator
                    </strong>

                    <span>
                        <?= escape($email) ?>
                    </span>

                </div>

            </div>

        </div>

    </header>


    <main class="admin-content">

        <?php if ($flash): ?>

            <div
                class="alert alert-<?= escape(
                    $flash['type']
                ) ?> alert-dismissible fade show shadow-sm"
                role="alert"
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