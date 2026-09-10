<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Leave Policies';


/*
|--------------------------------------------------------------------------
| Load Leave Types + Policies
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query(
        "SELECT
            lt.leave_type_id,
            lt.leave_type_name,
            lt.description,
            lt.is_paid,
            lt.status AS leave_type_status,

            lp.policy_id,
            lp.days_per_year,
            lp.minimum_service_months,
            lp.carry_forward_allowed,
            lp.max_carry_forward_days,
            lp.requires_attachment,
            lp.status AS policy_status,
            lp.created_at AS policy_created_at,
            lp.updated_at AS policy_updated_at

         FROM leave_types lt

         LEFT JOIN leave_policies lp
            ON lt.leave_type_id = lp.leave_type_id

         ORDER BY
            lt.status = 'Active' DESC,
            lt.leave_type_name ASC"
    );

    $policies = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log(
        'Leave policy list error: '
        . $e->getMessage()
    );

    $policies = [];
}


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

$totalTypes = count($policies);

$configuredPolicies = count(
    array_filter(
        $policies,
        static fn (array $policy): bool =>
            !empty($policy['policy_id'])
    )
);

$activePolicies = count(
    array_filter(
        $policies,
        static fn (array $policy): bool =>
            !empty($policy['policy_id'])
            &&
            $policy['policy_status'] === 'Active'
    )
);


require_once __DIR__
    . '/../includes/admin/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/admin/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Leave Policies
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
                Leave Policy Management
            </h2>

            <p>
                Configure entitlement, eligibility,
                carry-forward and attachment rules
                for each leave type.
            </p>

        </div>

    </div>

</div>


<!-- =========================================================
     SUMMARY
     ========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Leave Types
                </span>

                <strong>
                    <?= $totalTypes ?>
                </strong>

            </div>

            <div class="mini-summary-icon purple">

                <i class="bi bi-calendar3"></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Configured Policies
                </span>

                <strong>
                    <?= $configuredPolicies ?>
                </strong>

            </div>

            <div class="mini-summary-icon blue">

                <i
                    class="bi
                           bi-file-earmark-check"
                ></i>

            </div>

        </div>

    </div>


    <div class="col-sm-6 col-xl-4">

        <div class="mini-summary-card">

            <div>

                <span>
                    Active Policies
                </span>

                <strong>
                    <?= $activePolicies ?>
                </strong>

            </div>

            <div class="mini-summary-icon green">

                <i class="bi bi-shield-check"></i>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     POLICY TABLE
     ========================================================= -->

<div class="admin-card">

    <div class="admin-card-header">

        <div>

            <h5>
                Organizational Leave Policies
            </h5>

            <small class="text-muted">
                Each leave type can have one
                organizational policy.
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

                    <th>
                        Leave Type
                    </th>

                    <th>
                        Entitlement
                    </th>

                    <th>
                        Minimum Service
                    </th>

                    <th>
                        Carry Forward
                    </th>

                    <th>
                        Attachment
                    </th>

                    <th>
                        Status
                    </th>

                    <th class="text-end">
                        Actions
                    </th>

                </tr>

                </thead>


                <tbody>

                <?php if (!$policies): ?>

                    <tr>

                        <td
                            colspan="7"
                            class="text-center py-5"
                        >

                            <div class="empty-state">

                                <i
                                    class="bi
                                           bi-file-earmark-x"
                                ></i>

                                <h6>
                                    No leave types available
                                </h6>

                                <p>
                                    Add leave types before
                                    configuring policies.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>


                <?php foreach (
                    $policies as $policy
                ): ?>

                    <tr>

                        <!-- Leave Type -->

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
                                            $policy[
                                                'leave_type_name'
                                            ]
                                        ) ?>
                                    </strong>


                                    <span>

                                        <?= (int)$policy[
                                            'is_paid'
                                        ] === 1
                                            ? 'Paid Leave'
                                            : 'Unpaid Leave'
                                        ?>

                                    </span>

                                </div>

                            </div>

                        </td>


                        <?php if (
                            !empty(
                                $policy['policy_id']
                            )
                        ): ?>


                            <!-- Entitlement -->

                            <td>

                                <span
                                    class="policy-value"
                                >
                                    <?= number_format(
                                        (float)$policy[
                                            'days_per_year'
                                        ],
                                        2
                                    ) ?>
                                </span>

                                <small
                                    class="policy-unit"
                                >
                                    days / year
                                </small>

                            </td>


                            <!-- Minimum Service -->

                            <td>

                                <?php if (
                                    (int)$policy[
                                        'minimum_service_months'
                                    ] === 0
                                ): ?>

                                    <span class="text-muted">
                                        Immediate
                                    </span>

                                <?php else: ?>

                                    <strong>
                                        <?= (int)$policy[
                                            'minimum_service_months'
                                        ] ?>
                                    </strong>

                                    <small class="text-muted">
                                        month(s)
                                    </small>

                                <?php endif; ?>

                            </td>


                            <!-- Carry Forward -->

                            <td>

                                <?php if (
                                    (int)$policy[
                                        'carry_forward_allowed'
                                    ] === 1
                                ): ?>

                                    <span
                                        class="type-badge
                                               policy-ready"
                                    >

                                        <i
                                            class="bi
                                                   bi-arrow-repeat"
                                        ></i>

                                        Up to

                                        <?= number_format(
                                            (float)$policy[
                                                'max_carry_forward_days'
                                            ],
                                            2
                                        ) ?>

                                        days

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="type-badge
                                               unpaid-badge"
                                    >
                                        Not Allowed
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Attachment -->

                            <td>

                                <?php if (
                                    (int)$policy[
                                        'requires_attachment'
                                    ] === 1
                                ): ?>

                                    <span
                                        class="type-badge
                                               policy-ready"
                                    >

                                        <i
                                            class="bi
                                                   bi-paperclip"
                                        ></i>

                                        Required

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="type-badge
                                               unpaid-badge"
                                    >
                                        Optional
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <?php if (
                                    $policy['policy_status']
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


                            <!-- Actions -->

                            <td class="text-end">

                                <div
                                    class="d-inline-flex
                                           flex-wrap
                                           justify-content-end
                                           gap-2"
                                >

                                    <a
                                        href="/admin/configure-leave-policy.php?leave_type_id=<?= (int)$policy['leave_type_id'] ?>"
                                        class="btn
                                               btn-sm
                                               btn-outline-primary"
                                    >

                                        <i
                                            class="bi
                                                   bi-pencil"
                                        ></i>

                                        Edit

                                    </a>


                                    <form
                                        method="POST"
                                        action="/admin/toggle-leave-policy.php"
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
                                            name="policy_id"
                                            value="<?= (int)$policy[
                                                'policy_id'
                                            ] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn
                                                   btn-sm
                                                   btn-outline-secondary"
                                        >

                                            <?php if (
                                                $policy[
                                                    'policy_status'
                                                ]
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


                        <?php else: ?>


                            <td colspan="5">

                                <div
                                    class="policy-not-configured"
                                >

                                    <i
                                        class="bi
                                               bi-exclamation-circle"
                                    ></i>

                                    Policy has not been
                                    configured yet.

                                </div>

                            </td>


                            <td class="text-end">

                                <a
                                    href="/admin/configure-leave-policy.php?leave_type_id=<?= (int)$policy['leave_type_id'] ?>"
                                    class="btn-professional-primary"
                                >

                                    <i
                                        class="bi
                                               bi-sliders"
                                    ></i>

                                    Configure

                                </a>

                            </td>

                        <?php endif; ?>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/admin/footer.php';