<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');

$pageTitle = 'Add Leave Type';

$error = '';

$leaveTypeName = '';
$description = '';
$isPaidRaw = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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


    $leaveTypeNameInput =
        $_POST['leave_type_name']
        ?? '';


    $leaveTypeName =
        is_string(
            $leaveTypeNameInput
        )
            ? trim(
                $leaveTypeNameInput
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


    $isPaidInput =
        $_POST['is_paid']
        ?? '';


    $isPaidRaw =
        is_string(
            $isPaidInput
        )
            ? $isPaidInput
            : '';


    if ($leaveTypeName === '') {

        $error =
            'Leave type name is required.';

    } elseif (
        strlen($leaveTypeName) > 100
    ) {

        $error =
            'Leave type name cannot exceed 100 characters.';

    } elseif (
        strlen($description) > 255
    ) {

        $error =
            'Description cannot exceed 255 characters.';

    } elseif (
        !in_array(
            $isPaidRaw,
            ['0', '1'],
            true
        )
    ) {

        $error =
            'Please select whether the leave type is paid or unpaid.';

    } else {

        try {

            $duplicateStmt =
                $pdo->prepare(
                    "SELECT leave_type_id
                     FROM leave_types
                     WHERE leave_type_name =
                        :leave_type_name
                     LIMIT 1"
                );

            $duplicateStmt->execute([
                'leave_type_name' =>
                    $leaveTypeName
            ]);

            if (
                $duplicateStmt->fetch()
            ) {

                throw new RuntimeException(
                    'A leave type with this name already exists.'
                );
            }


            $stmt =
                $pdo->prepare(
                    "INSERT INTO leave_types
                    (
                        leave_type_name,
                        description,
                        is_paid,
                        status
                    )
                    VALUES
                    (
                        :leave_type_name,
                        :description,
                        :is_paid,
                        'Active'
                    )"
                );


            $stmt->execute([
                'leave_type_name' =>
                    $leaveTypeName,

                'description' =>
                    $description !== ''
                        ? $description
                        : null,

                'is_paid' =>
                    (int)$isPaidRaw
            ]);


            /*
            |--------------------------------------------------------------------------
            | Newly Created Leave Type ID
            |--------------------------------------------------------------------------
            */

            $leaveTypeId =
                (int)$pdo
                    ->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Audit Leave Type Creation
            |--------------------------------------------------------------------------
            */

            logAudit(
                $pdo,
                'LEAVE_TYPE_CREATED',
                'leave_type',
                $leaveTypeId,
                'Created leave type: '
                . $leaveTypeName
                . ' ('
                . (
                    (int)$isPaidRaw === 1
                        ? 'Paid'
                        : 'Unpaid'
                )
                . ').'
            );


            setFlash(
                'success',
                'Leave type created successfully.'
            );


            header(
                'Location: /admin/leave-types.php'
            );

            exit;


        } catch (Throwable $e) {

            error_log(
                'Add leave type error: ' .
                $e->getMessage()
            );


            $error =
                $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to create the leave type.';
        }
    }
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

        <a href="/admin/leave-types.php">
            Leave Types
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Add
        </span>

    </div>


    <h2>
        Add Leave Type
    </h2>

    <p>
        Create a new leave category
        for employees.
    </p>

</div>


<div class="row g-4">

    <div class="col-xl-8">

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Leave Type Information
                    </h5>

                    <small class="text-muted">
                        Define the leave category
                        and payment classification
                    </small>

                </div>

                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-calendar-plus"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <?php if ($error !== ''): ?>

                    <div
                        class="alert alert-danger
                               d-flex
                               align-items-center
                               gap-2"
                    >

                        <i
                            class="bi
                                   bi-exclamation-triangle-fill"
                        ></i>

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


                    <div class="mb-4">

                        <label
                            for="leave_type_name"
                            class="professional-form-label"
                        >
                            Leave Type Name

                            <span class="required-mark">
                                *
                            </span>
                        </label>


                        <input
                            type="text"
                            id="leave_type_name"
                            name="leave_type_name"
                            maxlength="100"
                            required
                            class="form-control
                                   professional-input"
                            placeholder="Example: Study Leave"
                            value="<?= escape(
                                $leaveTypeName
                            ) ?>"
                        >


                        <div class="form-help">
                            Use a clear and unique
                            leave category name.
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
                            maxlength="255"
                            rows="4"
                            class="form-control
                                   professional-input"
                            placeholder="Describe when this leave type should be used..."
                        ><?= escape(
                            $description
                        ) ?></textarea>

                    </div>


                    <div class="mb-4">

                        <label
                            class="professional-form-label"
                        >
                            Payment Classification

                            <span class="required-mark">
                                *
                            </span>
                        </label>


                        <div
                            class="row g-3"
                        >

                            <div class="col-md-6">

                                <label
                                    class="choice-card"
                                >

                                    <input
                                        type="radio"
                                        name="is_paid"
                                        value="1"
                                        required
                                        <?= (
                                            $isPaidRaw
                                            === '1'
                                        )
                                            ? 'checked'
                                            : ''
                                        ?>
                                    >

                                    <div
                                        class="choice-card-content"
                                    >

                                        <div
                                            class="choice-card-icon
                                                   paid"
                                        >
                                            <i
                                                class="bi
                                                       bi-cash-stack"
                                            ></i>
                                        </div>

                                        <div>

                                            <strong>
                                                Paid Leave
                                            </strong>

                                            <span>
                                                Employee remains
                                                eligible for pay.
                                            </span>

                                        </div>

                                    </div>

                                </label>

                            </div>


                            <div class="col-md-6">

                                <label
                                    class="choice-card"
                                >

                                    <input
                                        type="radio"
                                        name="is_paid"
                                        value="0"
                                        required
                                        <?= (
                                            $isPaidRaw
                                            === '0'
                                        )
                                            ? 'checked'
                                            : ''
                                        ?>
                                    >

                                    <div
                                        class="choice-card-content"
                                    >

                                        <div
                                            class="choice-card-icon
                                                   unpaid"
                                        >
                                            <i
                                                class="bi
                                                       bi-slash-circle"
                                            ></i>
                                        </div>

                                        <div>

                                            <strong>
                                                Unpaid Leave
                                            </strong>

                                            <span>
                                                Leave is recorded
                                                without paid entitlement.
                                            </span>

                                        </div>

                                    </div>

                                </label>

                            </div>

                        </div>

                    </div>


                    <div class="form-actions">

                        <a
                            href="/admin/leave-types.php"
                            class="btn-professional-secondary"
                        >

                            <i class="bi bi-arrow-left"></i>

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

                            Create Leave Type

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <div class="col-xl-4">

        <div class="admin-info-box">

            <div class="admin-info-icon">

                <i class="bi bi-lightbulb-fill"></i>

            </div>

            <div>

                <strong>
                    What's next?
                </strong>

                <p>
                    After creating a leave type,
                    you will configure its annual
                    entitlement and other rules
                    through Leave Policies.
                </p>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';