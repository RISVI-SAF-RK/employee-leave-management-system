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
    'Adjust Leave Balance';

$error = '';


/*
|--------------------------------------------------------------------------
| Balance ID
|--------------------------------------------------------------------------
*/

$balanceId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (!$balanceId) {

    setFlash(
        'danger',
        'Invalid leave balance selected.'
    );

    header(
        'Location: /admin/leave-balances.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Balance
|--------------------------------------------------------------------------
*/

$balanceStmt =
    $pdo->prepare(
        "SELECT
            lb.balance_id,
            lb.employee_id,
            lb.leave_type_id,
            lb.balance_year,
            lb.allocated_days,
            lb.used_days,
            lb.remaining_days,

            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,

            d.department_name,

            lt.leave_type_name

         FROM leave_balances lb

         INNER JOIN employees e
            ON lb.employee_id =
               e.employee_id

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         INNER JOIN leave_types lt
            ON lb.leave_type_id =
               lt.leave_type_id

         WHERE lb.balance_id =
            :balance_id

         LIMIT 1"
    );


$balanceStmt->execute([
    'balance_id' =>
        $balanceId
]);


$balance =
    $balanceStmt->fetch();


if (!$balance) {

    setFlash(
        'danger',
        'Leave balance not found.'
    );

    header(
        'Location: /admin/leave-balances.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Form Submit
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

        exit(
            'Invalid security token.'
        );
    }


    $allocatedRaw = trim(
        $_POST['allocated_days']
        ?? ''
    );


    $allocatedDays =
        filter_var(
            $allocatedRaw,
            FILTER_VALIDATE_FLOAT
        );


    $usedDays =
        (float)$balance[
            'used_days'
        ];


    if (
        $allocatedDays === false
        ||
        $allocatedDays < 0
        ||
        $allocatedDays > 999.99
    ) {

        $error =
            'Allocated days must be between 0 and 999.99.';

    } elseif (
        $allocatedDays < $usedDays
    ) {

        $error =
            'Allocated days cannot be lower than the already used leave amount.';

    } else {

        try {

            $remainingDays =
                round(
                    $allocatedDays
                    -
                    $usedDays,
                    2
                );


            $updateStmt =
                $pdo->prepare(
                    "UPDATE leave_balances

                     SET
                        allocated_days =
                            :allocated_days,

                        remaining_days =
                            :remaining_days

                     WHERE balance_id =
                        :balance_id"
                );


            $updateStmt->execute([

                'allocated_days' =>
                    $allocatedDays,

                'remaining_days' =>
                    $remainingDays,

                'balance_id' =>
                    $balanceId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Audit Leave Balance Adjustment
            |--------------------------------------------------------------------------
            */

            logAudit(
                $pdo,
                'LEAVE_BALANCE_ADJUSTED',
                'leave_balance',
                (int)$balanceId,
                'Administrator changed '
                . $balance[
                    'leave_type_name'
                ]
                . ' allocation for '
                . $balance[
                    'employee_code'
                ]
                . ' from '
                . number_format(
                    (float)$balance[
                        'allocated_days'
                    ],
                    2
                )
                . ' to '
                . number_format(
                    (float)$allocatedDays,
                    2
                )
                . ' day(s).'
            );


            setFlash(
                'success',
                'Leave balance adjusted successfully.'
            );


            header(
                'Location: /admin/leave-balances.php?year='
                . urlencode(
                    (string)$balance[
                        'balance_year'
                    ]
                )
            );

            exit;


        } catch (Throwable $e) {

            error_log(
                'Balance adjustment error: '
                . $e->getMessage()
            );


            $error =
                'Unable to adjust the leave balance.';
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


        <a
            href="/admin/leave-balances.php?year=<?= (int)$balance['balance_year'] ?>"
        >
            Leave Balances
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Adjust
        </span>

    </div>


    <h2>
        Adjust Leave Balance
    </h2>


    <p>
        Make an administrative adjustment
        without changing approved leave usage.
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


    <!-- FORM -->

    <div class="col-xl-8">

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Balance Adjustment
                    </h5>

                    <small class="text-muted">
                        Update total allocated
                        entitlement
                    </small>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-sliders"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <div
                    class="balance-person-header
                           mb-4"
                >

                    <div
                        class="employee-avatar
                               balance-large-avatar"
                    >

                        <?= escape(
                            strtoupper(
                                substr(
                                    $balance[
                                        'first_name'
                                    ],
                                    0,
                                    1
                                )
                                .
                                substr(
                                    $balance[
                                        'last_name'
                                    ],
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <h5 class="mb-1">

                            <?= escape(
                                $balance[
                                    'first_name'
                                ]
                                . ' '
                                . $balance[
                                    'last_name'
                                ]
                            ) ?>

                        </h5>


                        <div
                            class="text-muted small"
                        >

                            <?= escape(
                                $balance[
                                    'employee_code'
                                ]
                            ) ?>

                            ·

                            <?= escape(
                                $balance[
                                    'department_name'
                                ]
                            ) ?>

                        </div>

                    </div>

                </div>


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
                            Allocated Days

                            <span class="required-mark">
                                *
                            </span>
                        </label>


                        <div
                            class="policy-input-group
                                   policy-small-input"
                        >

                            <input
                                type="number"
                                name="allocated_days"
                                min="<?= number_format(
                                    (float)$balance[
                                        'used_days'
                                    ],
                                    2,
                                    '.',
                                    ''
                                ) ?>"
                                max="999.99"
                                step="0.5"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    number_format(
                                        (float)$balance[
                                            'allocated_days'
                                        ],
                                        2,
                                        '.',
                                        ''
                                    )
                                ) ?>"
                            >

                            <span>
                                days
                            </span>

                        </div>


                        <div class="form-help">
                            Used leave cannot be reduced
                            from this page. Remaining
                            balance will be recalculated
                            automatically.
                        </div>

                    </div>


                    <div class="form-actions">

                        <a
                            href="/admin/leave-balances.php?year=<?= (int)$balance['balance_year'] ?>"
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

                            Save Adjustment

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- DETAILS -->

    <div class="col-xl-4">

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <h5>
                        Current Balance
                    </h5>

                    <small class="text-muted">

                        <?= escape(
                            $balance[
                                'leave_type_name'
                            ]
                        ) ?>

                        ·

                        <?= (int)$balance[
                            'balance_year'
                        ] ?>

                    </small>

                </div>

            </div>


            <div class="admin-card-body">

                <div
                    class="balance-detail-grid"
                >

                    <div
                        class="balance-detail-item"
                    >

                        <span>
                            Allocated
                        </span>

                        <strong>
                            <?= number_format(
                                (float)$balance[
                                    'allocated_days'
                                ],
                                2
                            ) ?>
                        </strong>

                    </div>


                    <div
                        class="balance-detail-item"
                    >

                        <span>
                            Used
                        </span>

                        <strong>
                            <?= number_format(
                                (float)$balance[
                                    'used_days'
                                ],
                                2
                            ) ?>
                        </strong>

                    </div>


                    <div
                        class="balance-detail-item
                               remaining"
                    >

                        <span>
                            Remaining
                        </span>

                        <strong>
                            <?= number_format(
                                (float)$balance[
                                    'remaining_days'
                                ],
                                2
                            ) ?>
                        </strong>

                    </div>

                </div>


                <div
                    class="admin-info-box
                           mt-4"
                >

                    <div
                        class="admin-info-icon"
                    >

                        <i
                            class="bi
                                   bi-shield-check"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Data integrity
                        </strong>

                        <p>
                            Used leave is controlled by
                            approved applications and
                            cannot be manually changed
                            here.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/admin/footer.php';