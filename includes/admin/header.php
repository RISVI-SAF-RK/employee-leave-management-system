<?php

declare(strict_types=1);

require_once __DIR__ . '/../role_check.php';
require_once __DIR__ . '/../functions.php';

requireRole('Administrator');

$pageTitle = $pageTitle ?? 'Administrator';

$flash = getFlash();
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
        href="/assets/css/style.css"
    >

</head>

<body class="admin-body">

<nav class="navbar navbar-dark bg-dark">

    <div class="container-fluid">

        <a
            class="navbar-brand"
            href="/admin/dashboard.php"
        >
            ELMS Administration
        </a>

        <div class="d-flex align-items-center text-white">

            <span class="me-3">
                <?= escape(
                    $_SESSION['email'] ?? ''
                ) ?>
            </span>

            <a
                href="/logout.php"
                class="btn btn-outline-light btn-sm"
            >
                Logout
            </a>

        </div>

    </div>

</nav>

<div class="container-fluid">

    <div class="row">

        <aside
            class="col-md-2 admin-sidebar py-4"
        >

            <a href="/admin/dashboard.php">
                Dashboard
            </a>

            <a href="/admin/departments.php">
                Departments
            </a>

            <a href="#">
                Employees
            </a>

            <a href="#">
                Leave Types
            </a>

            <a href="#">
                Leave Policies
            </a>

            <a href="#">
                Leave Balances
            </a>

            <a href="#">
                Leave Records
            </a>

            <a href="#">
                Reports
            </a>

        </aside>

        <main
            class="col-md-10 ms-sm-auto px-md-4 py-4"
        >

            <?php if ($flash): ?>

                <div
                    class="alert alert-<?= escape(
                        $flash['type']
                    ) ?>"
                >

                    <?= escape(
                        $flash['message']
                    ) ?>

                </div>

            <?php endif; ?>