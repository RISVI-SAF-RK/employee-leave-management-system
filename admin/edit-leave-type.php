<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');

$pageTitle = 'Edit Leave Type';

$error = '';


$leaveTypeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (!$leaveTypeId) {

    setFlash(
        'danger',
        'Invalid leave type selected.'
    );

    header(
        'Location: /admin/leave-types.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Leave Type
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        leave_type_id,
        leave_type_name,
        description,
        is_paid,
        status,
        created_at,
        updated_at

     FROM leave_types

     WHERE leave_type_id =
        :leave_type_id

     LIMIT 1"
);


$stmt->execute([
    'leave_type_id' =>
        $leaveTypeId
]);


$leaveType =
    $stmt->fetch();


if (!$leaveType) {

    setFlash(
        'danger',
        'Leave type not found.'
    );

    header(
        'Location: /admin/leave-types.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token']
            ?? null
        )
    ) {

        http_response_code(403);

        exit('Invalid security token.');
    }


    $leaveTypeName = trim(
        $_POST['leave_type_name']
        ?? ''
    );

    $description = trim(
        $_POST['description']
        ?? ''
    );

    $isPaidRaw =
        $_POST['is_paid']
        ?? '';


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
                     WHERE
                        leave_type_name =
                            :leave_type_name
                        AND leave_type_id !=
                            :leave_type_id
                     LIMIT 1"
                );


            $duplicateStmt->execute([
                'leave_type_name' =>
                    $leaveTypeName,

                'leave_type_id' =>
                    $leaveTypeId
            ]);


            if (
                $duplicateStmt->fetch()
            ) {

                throw new RuntimeException(
                    'Another leave type already uses this name.'
                );
            }


            $updateStmt =
                $pdo->prepare(
                    "UPDATE leave_types
                     SET
                        leave_type_name =
                            :leave_type_name,

                        description =
                            :description,

                        is_paid =
                            :is_paid

                     WHERE leave_type_id =
                        :leave_type_id"
                );


            $updateStmt->execute([
                'leave_type_name' =>
                    $leaveTypeName,

                'description' =>
                    $description !== ''
                        ? $description
                        : null,

                'is_paid' =>
                    (int)$isPaidRaw,

                'leave_type_id' =>
                    $leaveTypeId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Audit Leave Type Update
            |--------------------------------------------------------------------------
            */

            logAudit(
                $pdo,
                'LEAVE_TYPE_UPDATED',
                'leave_type',
                (int)$leaveTypeId,
                'Updated leave type: '
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
                'Leave type updated successfully.'
            );


            header(
                'Location: /admin/leave-types.php'
            );

            exit;


        } catch (Throwable $e) {

            error_log(
                'Edit leave type error: ' .
                $e->getMessage()
            );


            $error =
                $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to update the leave type.';
        }
    }


    $leaveType['leave_type_name'] =
        $leaveTypeName;

    $leaveType['description'] =
        $description;

    $leaveType['is_paid'] =
        (int)$isPaidRaw;
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
            Edit
        </span>

    </div>


    <h2>
        Edit Leave Type
    </h2>

    <p>
        Update the leave category
        without affecting historical records.
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
                        Update category details
                    </small>

                </div>


                <?php if (
                    $leaveType['status']
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
                               alert-danger"
                    >

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
                            class="professional-form-label"
                        >
                            Leave Type Name

                            <span class="required-mark">
                                *
                            </span>
                        </label>


                        <input
                            type="text"
                            name="leave_type_name"
                            maxlength="100"
                            required
                            class="form-control
                                   professional-input"
                            value="<?= escape(
                                $leaveType[
                                    'leave_type_name'
                                ]
                            ) ?>"
                        >

                    </div>


                    <div class="mb-4">

                        <label
                            class="professional-form-label"
                        >
                            Description
                        </label>


                        <textarea
                            name="description"
                            maxlength="255"
                            rows="4"
                            class="form-control
                                   professional-input"
                        ><?= escape(
                            $leaveType[
                                'description'
                            ] ?? ''
                        ) ?></textarea>

                    </div>


                    <div class="mb-4">

                        <label
                            class="professional-form-label"
                        >
                            Payment Classification
                        </label>


                        <div class="row g-3">

                            <div class="col-md-6">

                                <label class="choice-card">

                                    <input
                                        type="radio"
                                        name="is_paid"
                                        value="1"
                                        required
                                        <?= (
                                            (int)$leaveType[
                                                'is_paid'
                                            ] === 1
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
                                                Paid employee entitlement
                                            </span>

                                        </div>

                                    </div>

                                </label>

                            </div>


                            <div class="col-md-6">

                                <label class="choice-card">

                                    <input
                                        type="radio"
                                        name="is_paid"
                                        value="0"
                                        required
                                        <?= (
                                            (int)$leaveType[
                                                'is_paid'
                                            ] === 0
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
                                                No paid entitlement
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

                            <i class="bi bi-check2-circle"></i>

                            Save Changes

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <div class="col-xl-4">

        <div class="admin-card">

            <div class="admin-card-header">

                <h5>
                    Record Details
                </h5>

            </div>

            <div class="admin-card-body">

                <div class="detail-list">

                    <div class="detail-item">

                        <div class="detail-icon">
                            <i class="bi bi-hash"></i>
                        </div>

                        <div>

                            <span>
                                Leave Type ID
                            </span>

                            <strong>
                                #<?= (int)$leaveTypeId ?>
                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">
                            <i class="bi bi-calendar-plus"></i>
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
                                            $leaveType[
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
                            <i class="bi bi-clock-history"></i>
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
                                            $leaveType[
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

    </div>

</div>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';