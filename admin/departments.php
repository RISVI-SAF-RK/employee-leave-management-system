<?php

declare(strict_types=1);

require_once __DIR__ .
    '/../config/database.php';

$pageTitle = 'Departments';

$stmt = $pdo->query(
    "SELECT
        department_id,
        department_name,
        description,
        status,
        created_at
     FROM departments
     ORDER BY department_name"
);

$departments = $stmt->fetchAll();

require_once __DIR__ .
    '/../includes/admin/header.php';
?>

<div
    class="d-flex justify-content-between
           align-items-center mb-4"
>

    <div>

        <h2>
            Department Management
        </h2>

        <p class="text-muted mb-0">
            Manage organizational departments.
        </p>

    </div>

    <a
        href="/admin/add-department.php"
        class="btn btn-dark"
    >
        + Add Department
    </a>

</div>

<div class="card border-0 shadow-sm">

    <div class="card-body">

        <div class="table-responsive">

            <table
                class="table table-hover align-middle"
            >

                <thead>

                <tr>

                    <th>ID</th>
                    <th>Department</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>

                </tr>

                </thead>

                <tbody>

                <?php if (!$departments): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted"
                        >
                            No departments found.
                        </td>

                    </tr>

                <?php endif; ?>

                <?php foreach (
                    $departments as $department
                ): ?>

                    <tr>

                        <td>
                            <?= (int)$department[
                                'department_id'
                            ] ?>
                        </td>

                        <td>
                            <?= escape(
                                $department[
                                    'department_name'
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= escape(
                                $department[
                                    'description'
                                ] ?? ''
                            ) ?>
                        </td>

                        <td>

                            <?php if (
                                $department['status']
                                === 'Active'
                            ): ?>

                                <span
                                    class="badge bg-success"
                                >
                                    Active
                                </span>

                            <?php else: ?>

                                <span
                                    class="badge bg-secondary"
                                >
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <a
                                href="/admin/edit-department.php?id=<?= (int)$department['department_id'] ?>"
                                class="btn btn-sm btn-outline-primary"
                            >
                                Edit
                            </a>

                            <form
                                method="POST"
                                action="/admin/toggle-department.php"
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
                                    name="department_id"
                                    value="<?= (int)$department[
                                        'department_id'
                                    ] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-secondary"
                                >

                                    <?= $department['status']
                                        === 'Active'
                                        ? 'Deactivate'
                                        : 'Activate'
                                    ?>

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