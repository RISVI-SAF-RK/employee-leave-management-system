<?php

declare(strict_types=1);

require_once __DIR__ .
    '/../config/database.php';

$pageTitle = 'Dashboard';

try {

    $employeeCount = (int)$pdo
        ->query(
            "SELECT COUNT(*)
             FROM employees
             WHERE status = 'Active'"
        )
        ->fetchColumn();


    $departmentCount = (int)$pdo
        ->query(
            "SELECT COUNT(*)
             FROM departments
             WHERE status = 'Active'"
        )
        ->fetchColumn();


    $pendingLeaveCount = (int)$pdo
        ->query(
            "SELECT COUNT(*)
             FROM leave_applications
             WHERE status = 'Pending'"
        )
        ->fetchColumn();


    $leaveTypeCount = (int)$pdo
        ->query(
            "SELECT COUNT(*)
             FROM leave_types
             WHERE status = 'Active'"
        )
        ->fetchColumn();

} catch (Throwable $e) {

    error_log(
        'Admin dashboard error: ' .
        $e->getMessage()
    );

    $employeeCount = 0;
    $departmentCount = 0;
    $pendingLeaveCount = 0;
    $leaveTypeCount = 0;
}


require_once __DIR__ .
    '/../includes/admin/header.php';
?>


<div class="page-heading">

    <h2>
        Welcome back, Administrator
    </h2>

    <p>
        Here's an overview of your Employee
        Leave Management System.
    </p>

</div>


<div class="row g-4">

    <div class="col-sm-6 col-xl-3">

        <div class="dashboard-stat-card">

            <div class="stat-card-top">

                <div>

                    <div class="stat-label">
                        Active Employees
                    </div>

                    <h3 class="stat-value">
                        <?= $employeeCount ?>
                    </h3>

                </div>

                <div
                    class="stat-icon
                           stat-icon-purple"
                >
                    <i class="bi bi-people-fill"></i>
                </div>

            </div>

            <div class="stat-footer">
                Currently active employees
            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="dashboard-stat-card">

            <div class="stat-card-top">

                <div>

                    <div class="stat-label">
                        Departments
                    </div>

                    <h3 class="stat-value">
                        <?= $departmentCount ?>
                    </h3>

                </div>

                <div
                    class="stat-icon
                           stat-icon-blue"
                >
                    <i class="bi bi-diagram-3-fill"></i>
                </div>

            </div>

            <div class="stat-footer">
                Active departments
            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="dashboard-stat-card">

            <div class="stat-card-top">

                <div>

                    <div class="stat-label">
                        Pending Requests
                    </div>

                    <h3 class="stat-value">
                        <?= $pendingLeaveCount ?>
                    </h3>

                </div>

                <div
                    class="stat-icon
                           stat-icon-orange"
                >
                    <i class="bi bi-hourglass-split"></i>
                </div>

            </div>

            <div class="stat-footer">
                Awaiting manager action
            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-3">

        <div class="dashboard-stat-card">

            <div class="stat-card-top">

                <div>

                    <div class="stat-label">
                        Active Leave Types
                    </div>

                    <h3 class="stat-value">
                        <?= $leaveTypeCount ?>
                    </h3>

                </div>

                <div
                    class="stat-icon
                           stat-icon-green"
                >
                    <i class="bi bi-calendar-check-fill"></i>
                </div>

            </div>

            <div class="stat-footer">
                Available leave categories
            </div>

        </div>

    </div>

</div>


<div class="row g-4 mt-1">

    <div class="col-xl-8">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <h5>
                        System Overview
                    </h5>

                    <small class="text-muted">
                        Current ELMS administration status
                    </small>

                </div>

                <span
                    class="badge
                           rounded-pill
                           text-bg-success"
                >
                    System Online
                </span>

            </div>

            <div class="admin-card-body">

                <div class="row g-3">

                    <div class="col-md-6">

                        <div class="quick-action">

                            <div class="quick-action-icon">

                                <i
                                    class="bi
                                           bi-shield-check"
                                ></i>

                            </div>

                            <div>

                                <strong>
                                    Secure Access
                                </strong>

                                <span>
                                    Role-based authentication
                                    is enabled
                                </span>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="quick-action">

                            <div class="quick-action-icon">

                                <i
                                    class="bi
                                           bi-database-check"
                                ></i>

                            </div>

                            <div>

                                <strong>
                                    Cloud Database
                                </strong>

                                <span>
                                    Railway MySQL is
                                    connected
                                </span>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="quick-action">

                            <div class="quick-action-icon">

                                <i
                                    class="bi
                                           bi-person-check"
                                ></i>

                            </div>

                            <div>

                                <strong>
                                    Administrator
                                </strong>

                                <span>
                                    Administration account
                                    is active
                                </span>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="quick-action">

                            <div class="quick-action-icon">

                                <i
                                    class="bi
                                           bi-cloud-check"
                                ></i>

                            </div>

                            <div>

                                <strong>
                                    Online Deployment
                                </strong>

                                <span>
                                    ELMS is running
                                    in the cloud
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-xl-4">

        <div class="admin-card h-100">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Quick Actions
                    </h5>

                    <small class="text-muted">
                        Common administration tasks
                    </small>

                </div>

            </div>

            <div class="admin-card-body">

                <div class="d-grid gap-3">

                    <a
                        href="/admin/departments.php"
                        class="quick-action"
                    >

                        <div class="quick-action-icon">

                            <i
                                class="bi
                                       bi-diagram-3"
                            ></i>

                        </div>

                        <div>

                            <strong>
                                Manage Departments
                            </strong>

                            <span>
                                Add and maintain
                                departments
                            </span>

                        </div>

                    </a>


                    <div
                        class="quick-action"
                        style="opacity: 0.6;"
                    >

                        <div class="quick-action-icon">

                            <i
                                class="bi
                                       bi-person-plus"
                            ></i>

                        </div>

                        <div>

                            <strong>
                                Add Employee
                            </strong>

                            <span>
                                Coming in the next phase
                            </span>

                        </div>

                    </div>


                    <div
                        class="quick-action"
                        style="opacity: 0.6;"
                    >

                        <div class="quick-action-icon">

                            <i
                                class="bi
                                       bi-calendar-plus"
                            ></i>

                        </div>

                        <div>

                            <strong>
                                Leave Policies
                            </strong>

                            <span>
                                Coming soon
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';