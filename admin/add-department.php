<?php

declare(strict_types=1);

require_once __DIR__ .
    '/../includes/role_check.php';

requireRole('Administrator');

require_once __DIR__ .
    '/../includes/functions.php';

require_once __DIR__ .
    '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {
        http_response_code(403);

        exit('Invalid security token.');
    }

    $departmentName = trim(
        $_POST['department_name'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    if ($departmentName === '') {

        $error =
            'Department name is required.';

    } elseif (
        strlen($departmentName) > 100
    ) {

        $error =
            'Department name is too long.';

    } else {

        try {

            $stmt = $pdo->prepare(
                "INSERT INTO departments
                    (
                        department_name,
                        description
                    )
                 VALUES
                    (
                        :department_name,
                        :description
                    )"
            );

            $stmt->execute([
                'department_name' =>
                    $departmentName,

                'description' =>
                    $description !== ''
                        ? $description
                        : null
            ]);

            setFlash(
                'success',
                'Department created successfully.'
            );

            header(
                'Location: /admin/departments.php'
            );

            exit;

        } catch (PDOException $e) {

            if (
                $e->getCode() === '23000'
            ) {

                $error =
                    'That department already exists.';

            } else {

                error_log(
                    'Add department error: ' .
                    $e->getMessage()
                );

                $error =
                    'Unable to create department.';
            }
        }
    }
}

$pageTitle = 'Add Department';

require_once __DIR__ .
    '/../includes/admin/header.php';
?>

<h2 class="mb-4">
    Add Department
</h2>

<div class="card border-0 shadow-sm">

    <div class="card-body">

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">

                <?= escape($error) ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(
                    generateCsrfToken()
                ) ?>"
            >

            <div class="mb-3">

                <label
                    for="department_name"
                    class="form-label"
                >
                    Department Name
                </label>

                <input
                    type="text"
                    id="department_name"
                    name="department_name"
                    maxlength="100"
                    required
                    class="form-control"
                    value="<?= escape(
                        $_POST[
                            'department_name'
                        ] ?? ''
                    ) ?>"
                >

            </div>

            <div class="mb-3">

                <label
                    for="description"
                    class="form-label"
                >
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="255"
                    class="form-control"
                    rows="3"
                ><?= escape(
                    $_POST['description']
                    ?? ''
                ) ?></textarea>

            </div>

            <button
                type="submit"
                class="btn btn-dark"
            >
                Save Department
            </button>

            <a
                href="/admin/departments.php"
                class="btn btn-outline-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</div>

<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';