<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');

$error = '';

/*
|--------------------------------------------------------------------------
| Get Department ID
|--------------------------------------------------------------------------
*/

$departmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$departmentId) {

    setFlash(
        'danger',
        'Invalid department selected.'
    );

    header(
        'Location: /admin/departments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Existing Department
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        department_id,
        department_name,
        description,
        status,
        created_at,
        updated_at
     FROM departments
     WHERE department_id = :department_id
     LIMIT 1"
);

$stmt->execute([
    'department_id' => $departmentId
]);

$department = $stmt->fetch();

if (!$department) {

    setFlash(
        'danger',
        'Department not found.'
    );

    header(
        'Location: /admin/departments.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Process Edit Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        http_response_code(403);

        exit(
            'Invalid security token.'
        );
    }


    $departmentName = trim(
        $_POST['department_name'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($departmentName === '') {

        $error =
            'Department name is required.';

    } elseif (
        strlen($departmentName) > 100
    ) {

        $error =
            'Department name cannot exceed 100 characters.';

    } elseif (
        strlen($description) > 255
    ) {

        $error =
            'Description cannot exceed 255 characters.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Department Name
            |--------------------------------------------------------------------------
            */

            $duplicateStmt = $pdo->prepare(
                "SELECT department_id
                 FROM departments
                 WHERE department_name = :department_name
                   AND department_id != :department_id
                 LIMIT 1"
            );

            $duplicateStmt->execute([
                'department_name' =>
                    $departmentName,

                'department_id' =>
                    $departmentId
            ]);

            if ($duplicateStmt->fetch()) {

                $error =
                    'Another department already uses this name.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Update Department
                |--------------------------------------------------------------------------
                */

                $updateStmt = $pdo->prepare(
                    "UPDATE departments
                     SET
                        department_name = :department_name,
                        description = :description
                     WHERE department_id = :department_id"
                );

                $updateStmt->execute([
                    'department_name' =>
                        $departmentName,

                    'description' =>
                        $description !== ''
                            ? $description
                            : null,

                    'department_id' =>
                        $departmentId
                ]);


                /*
                |--------------------------------------------------------------------------
                | Audit Department Update
                |--------------------------------------------------------------------------
                */

                logAudit(
                    $pdo,
                    'DEPARTMENT_UPDATED',
                    'department',
                    (int)$departmentId,
                    'Updated department: '
                    . $departmentName
                );


                setFlash(
                    'success',
                    'Department updated successfully.'
                );


                header(
                    'Location: /admin/departments.php'
                );

                exit;
            }

        } catch (Throwable $e) {

            error_log(
                'Edit department error: ' .
                $e->getMessage()
            );

            $error =
                'Unable to update the department. Please try again.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Preserve Submitted Values When Validation Fails
    |--------------------------------------------------------------------------
    */

    $department['department_name'] =
        $departmentName;

    $department['description'] =
        $description;
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'Edit Department';

require_once __DIR__ .
    '/../includes/admin/header.php';
?>


<div class="page-heading">

    <div>

        <div class="page-breadcrumb">

            <a
                href="/admin/dashboard.php"
            >
                Dashboard
            </a>

            <i
                class="bi bi-chevron-right"
            ></i>

            <a
                href="/admin/departments.php"
            >
                Departments
            </a>

            <i
                class="bi bi-chevron-right"
            ></i>

            <span>
                Edit
            </span>

        </div>


        <h2>
            Edit Department
        </h2>

        <p>
            Update department information while
            keeping existing employee relationships
            unchanged.
        </p>

    </div>

</div>


<div class="row g-4">

    <!-- =====================================================
         EDIT FORM
         ===================================================== -->

    <div class="col-xl-8">

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Department Information
                    </h5>

                    <small class="text-muted">
                        Modify the department details below.
                    </small>

                </div>


                <?php if (
                    $department['status']
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

            </div>


            <div class="admin-card-body">

                <?php if ($error !== ''): ?>

                    <div
                        class="alert
                               alert-danger
                               d-flex
                               align-items-center
                               gap-2"
                        role="alert"
                    >

                        <i
                            class="bi
                                   bi-exclamation-triangle-fill"
                        ></i>

                        <div>
                            <?= escape($error) ?>
                        </div>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action="/admin/edit-department.php?id=<?= (int)$departmentId ?>"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape(
                            generateCsrfToken()
                        ) ?>"
                    >


                    <div class="mb-4">

                        <label
                            for="department_name"
                            class="professional-form-label"
                        >

                            Department Name

                            <span class="required-mark">
                                *
                            </span>

                        </label>


                        <div class="input-icon-wrapper">

                            <i
                                class="bi
                                       bi-diagram-3
                                       input-icon"
                            ></i>

                            <input
                                type="text"
                                id="department_name"
                                name="department_name"
                                class="form-control
                                       professional-input
                                       with-icon"
                                maxlength="100"
                                required
                                autocomplete="off"
                                value="<?= escape(
                                    $department[
                                        'department_name'
                                    ]
                                ) ?>"
                            >

                        </div>


                        <div class="form-help">
                            Enter a unique department name,
                            for example Human Resources or
                            Information Technology.
                        </div>

                    </div>


                    <div class="mb-4">

                        <label
                            for="description"
                            class="professional-form-label"
                        >
                            Description
                        </label>


                        <textarea
                            id="description"
                            name="description"
                            class="form-control
                                   professional-input"
                            rows="5"
                            maxlength="255"
                            placeholder="Briefly describe the department..."
                        ><?= escape(
                            $department[
                                'description'
                            ] ?? ''
                        ) ?></textarea>


                        <div
                            class="d-flex
                                   justify-content-between
                                   form-help"
                        >

                            <span>
                                Optional department description.
                            </span>

                            <span>
                                Maximum 255 characters
                            </span>

                        </div>

                    </div>


                    <div class="form-actions">

                        <a
                            href="/admin/departments.php"
                            class="btn-professional-secondary"
                        >

                            <i
                                class="bi
                                       bi-arrow-left"
                            ></i>

                            Cancel

                        </a>


                        <button
                            type="submit"
                            class="btn-professional-primary"
                        >

                            <i
                                class="bi
                                       bi-check2-circle"
                            ></i>

                            Save Changes

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- =====================================================
         DEPARTMENT DETAILS
         ===================================================== -->

    <div class="col-xl-4">

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Department Details
                    </h5>

                    <small class="text-muted">
                        Current system information
                    </small>

                </div>

                <div
                    class="header-icon-box"
                >
                    <i
                        class="bi
                               bi-info-circle"
                    ></i>
                </div>

            </div>


            <div class="admin-card-body">

                <div class="detail-list">

                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-hash"
                            ></i>

                        </div>

                        <div>

                            <span>
                                Department ID
                            </span>

                            <strong>
                                #<?= (int)$department[
                                    'department_id'
                                ] ?>
                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-toggle-on"
                            ></i>

                        </div>

                        <div>

                            <span>
                                Current Status
                            </span>

                            <strong>
                                <?= escape(
                                    $department[
                                        'status'
                                    ]
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-calendar-plus"
                            ></i>

                        </div>

                        <div>

                            <span>
                                Created
                            </span>

                            <strong>
                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $department[
                                                'created_at'
                                            ]
                                        )
                                    )
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-clock-history"
                            ></i>

                        </div>

                        <div>

                            <span>
                                Last Updated
                            </span>

                            <strong>
                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $department[
                                                'updated_at'
                                            ]
                                        )
                                    )
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="admin-info-box">

            <div class="admin-info-icon">

                <i
                    class="bi bi-lightbulb-fill"
                ></i>

            </div>

            <div>

                <strong>
                    Department Status
                </strong>

                <p>
                    Activation and deactivation are
                    managed from the Department
                    Management page.
                </p>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';