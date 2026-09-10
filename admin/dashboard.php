<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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

<h2 class="mb-4">
    Administrator Dashboard
</h2>

<p class="text-muted">
    Overview of the Employee Leave Management System.
</p>

<div class="row g-4 mt-2">

    <div class="col-md-6 col-xl-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted">
                    Active Employees
                </h6>

                <h2>
                    <?= $employeeCount ?>
                </h2>

            </div>

        </div>

    </div>

    <div class="col-md-6 col-xl-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted">
                    Departments
                </h6>

                <h2>
                    <?= $departmentCount ?>
                </h2>

            </div>

        </div>

    </div>

    <div class="col-md-6 col-xl-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted">
                    Pending Leave Requests
                </h6>

                <h2>
                    <?= $pendingLeaveCount ?>
                </h2>

            </div>

        </div>

    </div>

    <div class="col-md-6 col-xl-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted">
                    Active Leave Types
                </h6>

                <h2>
                    <?= $leaveTypeCount ?>
                </h2>

            </div>

        </div>

    </div>

</div>

<div class="card border-0 shadow-sm mt-4">

    <div class="card-body">

        <h5>
            Welcome to ELMS Administration
        </h5>

        <p class="mb-0 text-muted">
            Use the navigation menu to manage
            employees, departments, leave policies
            and other system information.
        </p>

    </div>

</div>

<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';