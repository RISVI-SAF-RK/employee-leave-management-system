<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Leave Types';

try {

    $stmt = $pdo->query(
        "SELECT
            lt.leave_type_id,
            lt.leave_type_name,
            lt.description,
            lt.is_paid,
            lt.status,
            lt.created_at,
            lp.policy_id,
            lp.status AS policy_status

         FROM leave_types lt

         LEFT JOIN leave_policies lp
            ON lt.leave_type_id = lp.leave_type_id

         ORDER BY
            lt.status = 'Active' DESC,
            lt.leave_type_name ASC"
    );

    $leaveTypes = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log(
        'Leave type list error: ' .
        $e->getMessage()
    );

    $leaveTypes = [];
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
            Leave Types
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
                Leave Type Management
            </h2>

            <p>
                Configure the leave categories
                available within the organization.
            </p>

        </div>


        <a
            href="/admin/add-leave-type.php"
            class="btn-professional-primary"
        >
            <i class="bi bi-calendar-plus-fill"></i>

            Add Leave Type
        </a>

    </div>

</div>


<div class="row g-3 mb-4">

    <div class="col-sm-6 col-lg-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Total Types
                </span>

                <strong>
                    <?= count($leaveTypes) ?>
                </strong>

            </div>

            <div class="mini-summary-icon purple">

                <i class="bi bi-calendar3"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-lg-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Active
                </span>

                <strong>
                    <?= count(
                        array_filter(
                            $leaveTypes,
                            fn ($type) =>
                                $type['status']
                                === 'Active'
                        )
                    ) ?>
                </strong>

            </div>

            <div class="mini-summary-icon green">

                <i class="bi bi-check-circle"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-lg-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Paid Leave
                </span>

                <strong>
                    <?= count(
                        array_filter(
                            $leaveTypes,
                            fn ($type) =>
                                (int)$type['is_paid']
                                === 1
                        )
                    ) ?>
                </strong>

            </div>

            <div class="mini-summary-icon blue">

                <i class="bi bi-cash-stack"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-lg-3">

        <div class="mini-summary-card">

            <div>

                <span>
                    Policies Configured
                </span>

                <strong>
                    <?= count(
                        array_filter(
                            $leaveTypes,
                            fn ($type) =>
                                !empty(
                                    $type['policy_id']
                                )
                        )
                    ) ?>
                </strong>

            </div>

            <div class="mini-summary-icon orange">

                <i class="bi bi-file-earmark-check"></i>

            </div>

        </div>

    </div>

</div>


<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                Leave Categories
            </h5>

            <small class="text-muted">
                Manage availability,
                payment type and policy status
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
                    <th>Leave Type</th>
                    <th>Payment</th>
                    <th>Policy</th>
                    <th>Status</th>
                    <th class="text-end">
                        Actions
                    </th>
                </tr>

                </thead>


                <tbody>

                <?php if (!$leaveTypes): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center py-5"
                        >

                            <div class="empty-state">

                                <i class="bi bi-calendar-x"></i>

                                <h6>
                                    No leave types found
                                </h6>

                                <p>
                                    Create the first leave
                                    category for ELMS.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $leaveTypes as $leaveType
                ): ?>

                    <tr>

                        <td>

                            <div class="leave-type-cell">

                                <div class="leave-type-icon">

                                    <i
                                        class="bi
                                               bi-calendar-event"
                                    ></i>

                                </div>

                                <div>

                                    <strong>
                                        <?= escape(
                                            $leaveType[
                                                'leave_type_name'
                                            ]
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= escape(
                                            $leaveType[
                                                'description'
                                            ]
                                            ?? 'No description'
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?php if (
                                (int)$leaveType[
                                    'is_paid'
                                ] === 1
                            ): ?>

                                <span
                                    class="type-badge
                                           paid-badge"
                                >
                                    <i
                                        class="bi
                                               bi-check-circle"
                                    ></i>

                                    Paid
                                </span>

                            <?php else: ?>

                                <span
                                    class="type-badge
                                           unpaid-badge"
                                >
                                    <i
                                        class="bi
                                               bi-dash-circle"
                                    ></i>

                                    Unpaid
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $leaveType[
                                        'policy_id'
                                    ]
                                )
                            ): ?>

                                <span
                                    class="type-badge
                                           policy-ready"
                                >
                                    <i
                                        class="bi
                                               bi-file-earmark-check"
                                    ></i>

                                    Configured
                                </span>

                            <?php else: ?>

                                <span
                                    class="type-badge
                                           policy-missing"
                                >
                                    <i
                                        class="bi
                                               bi-exclamation-circle"
                                    ></i>

                                    Not Configured
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

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

                        </td>


                        <td class="text-end">

                            <div
                                class="d-inline-flex
                                       gap-2
                                       flex-wrap
                                       justify-content-end"
                            >

                                <a
                                    href="/admin/edit-leave-type.php?id=<?= (int)$leaveType['leave_type_id'] ?>"
                                    class="btn
                                           btn-sm
                                           btn-outline-primary"
                                >

                                    <i class="bi bi-pencil"></i>

                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    action="/admin/toggle-leave-type.php"
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
                                        name="leave_type_id"
                                        value="<?= (int)$leaveType['leave_type_id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="btn
                                               btn-sm
                                               btn-outline-secondary"
                                    >

                                        <?php if (
                                            $leaveType['status']
                                            === 'Active'
                                        ): ?>

                                            <i
                                                class="bi
                                                       bi-pause-circle"
                                            ></i>

                                            Deactivate

                                        <?php else: ?>

                                            <i
                                                class="bi
                                                       bi-play-circle"
                                            ></i>

                                            Activate

                                        <?php endif; ?>

                                    </button>

                                </form>

                            </div>

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