<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Administrator');


$pageTitle =
    'Configure Leave Policy';

$error = '';


/*
|--------------------------------------------------------------------------
| Get Leave Type ID
|--------------------------------------------------------------------------
*/

$leaveTypeId = filter_input(
    INPUT_GET,
    'leave_type_id',
    FILTER_VALIDATE_INT
);


if (!$leaveTypeId) {

    setFlash(
        'danger',
        'Invalid leave type selected.'
    );

    header(
        'Location: /admin/leave-policies.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Leave Type
|--------------------------------------------------------------------------
*/

$leaveTypeStmt =
    $pdo->prepare(
        "SELECT
            leave_type_id,
            leave_type_name,
            description,
            is_paid,
            status

         FROM leave_types

         WHERE leave_type_id =
            :leave_type_id

         LIMIT 1"
    );


$leaveTypeStmt->execute([
    'leave_type_id' =>
        $leaveTypeId
]);


$leaveType =
    $leaveTypeStmt->fetch();


if (!$leaveType) {

    setFlash(
        'danger',
        'Leave type not found.'
    );

    header(
        'Location: /admin/leave-policies.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Existing Policy
|--------------------------------------------------------------------------
*/

$policyStmt =
    $pdo->prepare(
        "SELECT
            policy_id,
            leave_type_id,
            days_per_year,
            minimum_service_months,
            carry_forward_allowed,
            max_carry_forward_days,
            requires_attachment,
            status,
            created_at,
            updated_at

         FROM leave_policies

         WHERE leave_type_id =
            :leave_type_id

         LIMIT 1"
    );


$policyStmt->execute([
    'leave_type_id' =>
        $leaveTypeId
]);


$policy =
    $policyStmt->fetch();


/*
|--------------------------------------------------------------------------
| Form Defaults
|--------------------------------------------------------------------------
*/

$formData = [

    'days_per_year' =>
        $policy[
            'days_per_year'
        ] ?? '',

    'minimum_service_months' =>
        $policy[
            'minimum_service_months'
        ] ?? '0',

    'carry_forward_allowed' =>
        $policy[
            'carry_forward_allowed'
        ] ?? '0',

    'max_carry_forward_days' =>
        $policy[
            'max_carry_forward_days'
        ] ?? '0',

    'requires_attachment' =>
        $policy[
            'requires_attachment'
        ] ?? '0',

    'status' =>
        $policy[
            'status'
        ] ?? 'Active'
];


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !verifyCsrfToken(
            $_POST['csrf_token']
            ?? null
        )
    ) {

        http_response_code(403);

        exit(
            'Invalid security token.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Read Form
    |--------------------------------------------------------------------------
    */

    $formData['days_per_year'] =
        trim(
            $_POST[
                'days_per_year'
            ] ?? ''
        );


    $formData[
        'minimum_service_months'
    ] =
        trim(
            $_POST[
                'minimum_service_months'
            ] ?? '0'
        );


    $formData[
        'carry_forward_allowed'
    ] =
        $_POST[
            'carry_forward_allowed'
        ] ?? '0';


    $formData[
        'max_carry_forward_days'
    ] =
        trim(
            $_POST[
                'max_carry_forward_days'
            ] ?? '0'
        );


    $formData[
        'requires_attachment'
    ] =
        $_POST[
            'requires_attachment'
        ] ?? '0';


    $formData['status'] =
        $_POST['status']
        ?? 'Active';


    /*
    |--------------------------------------------------------------------------
    | Convert / Validate Numeric Values
    |--------------------------------------------------------------------------
    */

    $daysPerYear =
        filter_var(
            $formData[
                'days_per_year'
            ],
            FILTER_VALIDATE_FLOAT
        );


    $minimumServiceMonths =
        filter_var(
            $formData[
                'minimum_service_months'
            ],
            FILTER_VALIDATE_INT
        );


    $maxCarryForwardDays =
        filter_var(
            $formData[
                'max_carry_forward_days'
            ],
            FILTER_VALIDATE_FLOAT
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $daysPerYear === false
        ||
        $daysPerYear < 0
        ||
        $daysPerYear > 365
    ) {

        $error =
            'Annual entitlement must be between 0 and 365 days.';

    } elseif (
        $minimumServiceMonths === false
        ||
        $minimumServiceMonths < 0
        ||
        $minimumServiceMonths > 600
    ) {

        $error =
            'Minimum service must be between 0 and 600 months.';

    } elseif (
        !in_array(
            $formData[
                'carry_forward_allowed'
            ],
            ['0', '1'],
            true
        )
    ) {

        $error =
            'Invalid carry-forward setting.';

    } elseif (
        !in_array(
            $formData[
                'requires_attachment'
            ],
            ['0', '1'],
            true
        )
    ) {

        $error =
            'Invalid attachment setting.';

    } elseif (
        !in_array(
            $formData['status'],
            [
                'Active',
                'Inactive'
            ],
            true
        )
    ) {

        $error =
            'Invalid policy status.';

    }


    /*
    |--------------------------------------------------------------------------
    | Carry Forward Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $carryForwardAllowed =
            (int)$formData[
                'carry_forward_allowed'
            ];


        if (
            $carryForwardAllowed === 0
        ) {

            $maxCarryForwardDays =
                0.00;

            $formData[
                'max_carry_forward_days'
            ] = '0';

        } else {

            if (
                $maxCarryForwardDays === false
                ||
                $maxCarryForwardDays < 0
            ) {

                $error =
                    'Maximum carry-forward days must be zero or greater.';

            } elseif (
                $maxCarryForwardDays
                >
                $daysPerYear
            ) {

                $error =
                    'Maximum carry-forward days cannot exceed the annual entitlement.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | Existing Policy → UPDATE
            |--------------------------------------------------------------------------
            */

            if ($policy) {

                $updateStmt =
                    $pdo->prepare(
                        "UPDATE leave_policies

                         SET
                            days_per_year =
                                :days_per_year,

                            minimum_service_months =
                                :minimum_service_months,

                            carry_forward_allowed =
                                :carry_forward_allowed,

                            max_carry_forward_days =
                                :max_carry_forward_days,

                            requires_attachment =
                                :requires_attachment,

                            status =
                                :status

                         WHERE policy_id =
                            :policy_id"
                    );


                $updateStmt->execute([

                    'days_per_year' =>
                        $daysPerYear,

                    'minimum_service_months' =>
                        $minimumServiceMonths,

                    'carry_forward_allowed' =>
                        $carryForwardAllowed,

                    'max_carry_forward_days' =>
                        $maxCarryForwardDays,

                    'requires_attachment' =>
                        (int)$formData[
                            'requires_attachment'
                        ],

                    'status' =>
                        $formData['status'],

                    'policy_id' =>
                        $policy[
                            'policy_id'
                        ]
                ]);


                $successMessage =
                    'Leave policy updated successfully.';


            /*
            |--------------------------------------------------------------------------
            | New Policy → INSERT
            |--------------------------------------------------------------------------
            */

            } else {

                $insertStmt =
                    $pdo->prepare(
                        "INSERT INTO leave_policies
                        (
                            leave_type_id,
                            days_per_year,
                            minimum_service_months,
                            carry_forward_allowed,
                            max_carry_forward_days,
                            requires_attachment,
                            status
                        )

                        VALUES
                        (
                            :leave_type_id,
                            :days_per_year,
                            :minimum_service_months,
                            :carry_forward_allowed,
                            :max_carry_forward_days,
                            :requires_attachment,
                            :status
                        )"
                    );


                $insertStmt->execute([

                    'leave_type_id' =>
                        $leaveTypeId,

                    'days_per_year' =>
                        $daysPerYear,

                    'minimum_service_months' =>
                        $minimumServiceMonths,

                    'carry_forward_allowed' =>
                        $carryForwardAllowed,

                    'max_carry_forward_days' =>
                        $maxCarryForwardDays,

                    'requires_attachment' =>
                        (int)$formData[
                            'requires_attachment'
                        ],

                    'status' =>
                        $formData['status']
                ]);


                $successMessage =
                    'Leave policy configured successfully.';
            }


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            setFlash(
                'success',
                $successMessage
            );


            header(
                'Location: /admin/leave-policies.php'
            );

            exit;


        } catch (Throwable $e) {

            error_log(
                'Configure leave policy error: '
                . $e->getMessage()
            );


            $error =
                'Unable to save the leave policy. Please try again.';
        }
    }
}


require_once __DIR__
    . '/../includes/admin/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/admin/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <a href="/admin/leave-policies.php">
            Leave Policies
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            <?= $policy
                ? 'Edit'
                : 'Configure'
            ?>
        </span>

    </div>


    <h2>

        <?= $policy
            ? 'Edit Leave Policy'
            : 'Configure Leave Policy'
        ?>

    </h2>


    <p>
        Define organizational rules for

        <strong>
            <?= escape(
                $leaveType[
                    'leave_type_name'
                ]
            ) ?>
        </strong>.
    </p>

</div>


<?php if ($error !== ''): ?>

    <div
        class="alert
               alert-danger
               d-flex
               align-items-center
               gap-2"
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


<div class="row g-4">

    <!-- =====================================================
         POLICY FORM
         ===================================================== -->

    <div class="col-xl-8">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(
                    generateCsrfToken()
                ) ?>"
            >


            <!-- =================================================
                 ENTITLEMENT
                 ================================================= -->

            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Entitlement & Eligibility
                        </h5>

                        <small class="text-muted">
                            Annual entitlement and
                            minimum service requirements
                        </small>

                    </div>


                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-calendar2-week"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="row g-4">


                        <!-- Annual Entitlement -->

                        <div class="col-md-6">

                            <label
                                for="days_per_year"
                                class="professional-form-label"
                            >

                                Annual Entitlement

                                <span class="required-mark">
                                    *
                                </span>

                            </label>


                            <div class="policy-input-group">

                                <input
                                    type="number"
                                    id="days_per_year"
                                    name="days_per_year"
                                    min="0"
                                    max="365"
                                    step="0.5"
                                    required
                                    class="form-control
                                           professional-input"
                                    placeholder="14"
                                    value="<?= escape(
                                        (string)$formData[
                                            'days_per_year'
                                        ]
                                    ) ?>"
                                >


                                <span>
                                    days
                                </span>

                            </div>


                            <div class="form-help">
                                Leave days allocated
                                to an eligible employee
                                per year.
                            </div>

                        </div>


                        <!-- Minimum Service -->

                        <div class="col-md-6">

                            <label
                                for="minimum_service_months"
                                class="professional-form-label"
                            >

                                Minimum Service

                            </label>


                            <div class="policy-input-group">

                                <input
                                    type="number"
                                    id="minimum_service_months"
                                    name="minimum_service_months"
                                    min="0"
                                    max="600"
                                    step="1"
                                    required
                                    class="form-control
                                           professional-input"
                                    value="<?= escape(
                                        (string)$formData[
                                            'minimum_service_months'
                                        ]
                                    ) ?>"
                                >


                                <span>
                                    months
                                </span>

                            </div>


                            <div class="form-help">
                                Enter 0 when employees
                                qualify immediately.
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 CARRY FORWARD
                 ================================================= -->

            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Carry-Forward Rules
                        </h5>

                        <small class="text-muted">
                            Configure handling of
                            unused leave entitlement
                        </small>

                    </div>


                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-arrow-repeat"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="policy-setting-row">

                        <div>

                            <strong>
                                Allow Carry Forward
                            </strong>

                            <p>
                                Allow unused leave days
                                to continue into a future
                                leave year.
                            </p>

                        </div>


                        <div
                            class="policy-radio-group"
                        >

                            <label>

                                <input
                                    type="radio"
                                    name="carry_forward_allowed"
                                    value="1"
                                    <?= (
                                        (string)$formData[
                                            'carry_forward_allowed'
                                        ] === '1'
                                    )
                                        ? 'checked'
                                        : ''
                                    ?>
                                >

                                <span>
                                    Yes
                                </span>

                            </label>


                            <label>

                                <input
                                    type="radio"
                                    name="carry_forward_allowed"
                                    value="0"
                                    <?= (
                                        (string)$formData[
                                            'carry_forward_allowed'
                                        ] !== '1'
                                    )
                                        ? 'checked'
                                        : ''
                                    ?>
                                >

                                <span>
                                    No
                                </span>

                            </label>

                        </div>

                    </div>


                    <div
                        id="carryForwardLimit"
                        class="mt-4"
                    >

                        <label
                            for="max_carry_forward_days"
                            class="professional-form-label"
                        >

                            Maximum Carry-Forward Days

                        </label>


                        <div
                            class="policy-input-group
                                   policy-small-input"
                        >

                            <input
                                type="number"
                                id="max_carry_forward_days"
                                name="max_carry_forward_days"
                                min="0"
                                max="365"
                                step="0.5"
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    (string)$formData[
                                        'max_carry_forward_days'
                                    ]
                                ) ?>"
                            >


                            <span>
                                days
                            </span>

                        </div>


                        <div class="form-help">
                            This cannot exceed the
                            annual entitlement.
                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 REQUIREMENTS
                 ================================================= -->

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Application Requirements
                        </h5>

                        <small class="text-muted">
                            Supporting-document and
                            policy availability settings
                        </small>

                    </div>


                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-file-earmark-check"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="row g-4">


                        <!-- Attachment -->

                        <div class="col-md-6">

                            <label
                                for="requires_attachment"
                                class="professional-form-label"
                            >

                                Supporting Attachment

                            </label>


                            <select
                                id="requires_attachment"
                                name="requires_attachment"
                                class="form-select
                                       professional-input"
                            >

                                <option
                                    value="0"
                                    <?= (
                                        (string)$formData[
                                            'requires_attachment'
                                        ] === '0'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Optional
                                </option>


                                <option
                                    value="1"
                                    <?= (
                                        (string)$formData[
                                            'requires_attachment'
                                        ] === '1'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Required
                                </option>

                            </select>


                            <div class="form-help">
                                Required policies will later
                                force employees to submit
                                supporting evidence.
                            </div>

                        </div>


                        <!-- Status -->

                        <div class="col-md-6">

                            <label
                                for="status"
                                class="professional-form-label"
                            >

                                Policy Status

                            </label>


                            <select
                                id="status"
                                name="status"
                                class="form-select
                                       professional-input"
                            >

                                <option
                                    value="Active"
                                    <?= (
                                        $formData['status']
                                        === 'Active'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Active
                                </option>


                                <option
                                    value="Inactive"
                                    <?= (
                                        $formData['status']
                                        === 'Inactive'
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Inactive
                                </option>

                            </select>


                            <div class="form-help">
                                Only active policies will
                                later be available during
                                leave processing.
                            </div>

                        </div>

                    </div>


                    <!-- Actions -->

                    <div class="form-actions">

                        <a
                            href="/admin/leave-policies.php"
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


                            <?= $policy
                                ? 'Save Changes'
                                : 'Save Policy'
                            ?>

                        </button>

                    </div>

                </div>

            </div>

        </form>

    </div>


    <!-- =====================================================
         SIDE INFORMATION
         ===================================================== -->

    <div class="col-xl-4">

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Leave Type
                    </h5>

                    <small class="text-muted">
                        This policy applies to
                    </small>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="policy-type-preview">

                    <div class="policy-preview-icon">

                        <i
                            class="bi
                                   bi-calendar-event"
                        ></i>

                    </div>


                    <div>

                        <h5>
                            <?= escape(
                                $leaveType[
                                    'leave_type_name'
                                ]
                            ) ?>
                        </h5>


                        <span>

                            <?= (int)$leaveType[
                                'is_paid'
                            ] === 1
                                ? 'Paid Leave'
                                : 'Unpaid Leave'
                            ?>

                        </span>

                    </div>

                </div>


                <?php if (
                    !empty(
                        $leaveType[
                            'description'
                        ]
                    )
                ): ?>

                    <p
                        class="text-muted
                               small
                               mt-3
                               mb-0"
                    >

                        <?= escape(
                            $leaveType[
                                'description'
                            ]
                        ) ?>

                    </p>

                <?php endif; ?>

            </div>

        </div>


        <div class="admin-info-box">

            <div class="admin-info-icon">

                <i
                    class="bi
                           bi-info-circle-fill"
                ></i>

            </div>


            <div>

                <strong>
                    Policy-driven ELMS
                </strong>

                <p>
                    These rules will later be used
                    to validate applications and
                    calculate employee leave
                    balances automatically.
                </p>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/admin/footer.php';