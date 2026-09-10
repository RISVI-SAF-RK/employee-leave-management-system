<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Employees';

try {

    $stmt = $pdo->query(
        "SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,
            e.phone,
            e.status,
            u.email,
            r.role_name,
            d.department_name,
            CONCAT(
                m.first_name,
                ' ',
                m.last_name
            ) AS manager_name
         FROM employees e

         INNER JOIN users u
            ON e.user_id = u.user_id

         INNER JOIN roles r
            ON u.role_id = r.role_id

         INNER JOIN departments d
            ON e.department_id = d.department_id

         LEFT JOIN employees m
            ON e.manager_id = m.employee_id

         ORDER BY
            e.first_name,
            e.last_name"
    );

    $employees = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log(
        'Employee list error: ' .
        $e->getMessage()
    );

    $employees = [];
}

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
            Employees
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
                Employee Management
            </h2>

            <p>
                Manage employee profiles,
                accounts, roles and reporting
                relationships.
            </p>

        </div>


        <a
            href="/admin/add-employee.php"
            class="btn-professional-primary"
        >
            <i class="bi bi-person-plus-fill"></i>

            Add Employee
        </a>

    </div>

</div>


<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                Employees
            </h5>

            <small class="text-muted">
                <?= count($employees) ?>
                employee record(s)
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
                    <th>Role</th>
                    <th>Department</th>
                    <th>Manager</th>
                    <th>Status</th>
                    <th class="text-end">
                        Actions
                    </th>
                </tr>

                </thead>


                <tbody>

                <?php if (!$employees): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center
                                   py-5"
                        >

                            <div class="empty-state">

                                <i
                                    class="bi
                                           bi-people"
                                ></i>

                                <h6>
                                    No employees yet
                                </h6>

                                <p>
                                    Add your first Manager
                                    or Employee account.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $employees as $employee
                ): ?>

                    <?php
                    $fullName =
                        $employee['first_name']
                        . ' '
                        . $employee['last_name'];

                    $initials =
                        strtoupper(
                            substr(
                                $employee['first_name'],
                                0,
                                1
                            )
                        )
                        .
                        strtoupper(
                            substr(
                                $employee['last_name'],
                                0,
                                1
                            )
                        );
                    ?>

                    <tr>

                        <td>

                            <div
                                class="employee-cell"
                            >

                                <div
                                    class="employee-avatar"
                                >
                                    <?= escape(
                                        $initials
                                    ) ?>
                                </div>

                                <div
                                    class="employee-info"
                                >

                                    <strong>
                                        <?= escape(
                                            $fullName
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= escape(
                                            $employee[
                                                'employee_code'
                                            ]
                                        ) ?>
                                        •
                                        <?= escape(
                                            $employee[
                                                'email'
                                            ]
                                        ) ?>
                                    </span>

                                    <small>
                                        <?= escape(
                                            $employee[
                                                'job_title'
                                            ]
                                        ) ?>
                                    </small>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?php if (
                                $employee['role_name']
                                === 'Manager'
                            ): ?>

                                <span
                                    class="role-badge
                                           role-manager"
                                >
                                    <i
                                        class="bi
                                               bi-person-badge"
                                    ></i>

                                    Manager
                                </span>

                            <?php else: ?>

                                <span
                                    class="role-badge
                                           role-employee"
                                >
                                    <i
                                        class="bi
                                               bi-person"
                                    ></i>

                                    <?= escape(
                                        $employee[
                                            'role_name'
                                        ]
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>
                            <?= escape(
                                $employee[
                                    'department_name'
                                ]
                            ) ?>
                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $employee[
                                        'manager_name'
                                    ]
                                )
                            ): ?>

                                <?= escape(
                                    $employee[
                                        'manager_name'
                                    ]
                                ) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                $employee['status']
                                === 'Active'
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


                        <td class="text-end">

                            <a
                                href="/admin/edit-employee.php?id=<?= (int)$employee['employee_id'] ?>"
                                class="btn
                                       btn-sm
                                       btn-outline-primary"
                            >
                                <i class="bi bi-pencil"></i>

                                Edit
                            </a>


                            <form
                                method="POST"
                                action="/admin/toggle-employee.php"
                                class="d-inline"
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
                                    name="employee_id"
                                    value="<?= (int)$employee[
                                        'employee_id'
                                    ] ?>"
                                >


                                <button
                                    type="submit"
                                    class="btn
                                           btn-sm
                                           btn-outline-secondary"
                                >

                                    <?php if (
                                        $employee['status']
                                        === 'Active'
                                    ): ?>

                                        <i
                                            class="bi
                                                   bi-person-x"
                                        ></i>

                                        Deactivate

                                    <?php else: ?>

                                        <i
                                            class="bi
                                                   bi-person-check"
                                        ></i>

                                        Activate

                                    <?php endif; ?>

                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';