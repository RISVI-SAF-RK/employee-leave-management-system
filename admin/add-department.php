<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

requireRole('Administrator');

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


$error = '';

$departmentName = '';
$description = '';


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    $csrfToken =
        $_POST['csrf_token']
        ?? null;


    if (
        !is_string(
            $csrfToken
        )
        ||
        !verifyCsrfToken(
            $csrfToken
        )
    ) {

        http_response_code(403);

        exit(
            'Invalid security token.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Input
    |--------------------------------------------------------------------------
    */

    $departmentNameInput =
        $_POST['department_name']
        ?? '';


    $departmentName =
        is_string(
            $departmentNameInput
        )
            ? trim(
                $departmentNameInput
            )
            : '';


    $descriptionInput =
        $_POST['description']
        ?? '';


    $description =
        is_string(
            $descriptionInput
        )
            ? trim(
                $descriptionInput
            )
            : '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($departmentName === '') {

        $error =
            'Department name is required.';


    } elseif (
        strlen(
            $departmentName
        ) > 100
    ) {

        $error =
            'Department name cannot exceed 100 characters.';


    } elseif (
        strlen(
            $description
        ) > 255
    ) {

        $error =
            'Description cannot exceed 255 characters.';


    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Create Department
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare(
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


            /*
            |--------------------------------------------------------------------------
            | Newly Created Department ID
            |--------------------------------------------------------------------------
            */

            $departmentId =
                (int)$pdo
                    ->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Audit Department Creation
            |--------------------------------------------------------------------------
            */

            logAudit(
                $pdo,
                'DEPARTMENT_CREATED',
                'department',
                $departmentId,
                'Created department: '
                . $departmentName
            );


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            setFlash(
                'success',
                'Department created successfully.'
            );


            header(
                'Location: /admin/departments.php'
            );

            exit;


        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Duplicate Department
            |--------------------------------------------------------------------------
            */

            if (
                $e->getCode() === '23000'
            ) {

                $error =
                    'That department already exists.';


            } else {

                error_log(
                    'Add department error: '
                    . $e->getMessage()
                );


                $error =
                    'Unable to create department.';
            }
        }
    }
}


$pageTitle =
    'Add Department';


require_once __DIR__
    . '/../includes/admin/header.php';

?>


<h2 class="mb-4">
    Add Department
</h2>


<div class="card border-0 shadow-sm">

    <div class="card-body">

        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">

                <?= escape(
                    $error
                ) ?>

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
                        $departmentName
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
                    $description
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

require_once __DIR__
    . '/../includes/admin/footer.php';
