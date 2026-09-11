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
    'Leave Record Details';


$applicationId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (!$applicationId) {

    setFlash(
        'danger',
        'Invalid leave record.'
    );

    header(
        'Location: /admin/leave-records.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Complete Leave Record
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare(
        "SELECT
            la.application_id,
            la.employee_id,
            la.leave_type_id,
            la.start_date,
            la.end_date,
            la.number_of_days,
            la.reason,
            la.attachment,
            la.status,
            la.applied_at,
            la.updated_at,

            e.employee_code,
            e.first_name,
            e.last_name,
            e.phone,
            e.job_title,
            e.date_joined,

            u.email,

            d.department_name,

            lt.leave_type_name,
            lt.description AS leave_type_description,
            lt.is_paid,

            lp.days_per_year,
            lp.minimum_service_months,
            lp.requires_attachment,

            lb.allocated_days,
            lb.used_days,
            lb.remaining_days,

            lap.decision,
            lap.comment,
            lap.decision_date,

            manager.employee_code
                AS manager_code,

            CONCAT(
                manager.first_name,
                ' ',
                manager.last_name
            ) AS manager_name

         FROM leave_applications la

         INNER JOIN employees e
            ON la.employee_id =
               e.employee_id

         INNER JOIN users u
            ON e.user_id =
               u.user_id

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         INNER JOIN leave_types lt
            ON la.leave_type_id =
               lt.leave_type_id

         LEFT JOIN leave_policies lp
            ON la.leave_type_id =
               lp.leave_type_id

         LEFT JOIN leave_balances lb
            ON lb.employee_id =
               la.employee_id

            AND lb.leave_type_id =
               la.leave_type_id

            AND lb.balance_year =
               YEAR(
                   la.start_date
               )

         LEFT JOIN leave_approvals lap
            ON la.application_id =
               lap.application_id

         LEFT JOIN employees manager
            ON lap.manager_id =
               manager.employee_id

         WHERE la.application_id =
            :application_id

         LIMIT 1"
    );


$stmt->execute([
    'application_id' =>
        $applicationId
]);


$record =
    $stmt->fetch();


if (!$record) {

    http_response_code(404);

    exit(
        'Leave record not found.'
    );
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

        <a href="/admin/leave-records.php">
            Leave Records
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            #<?= $applicationId ?>
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
                Leave Record
                #<?= $applicationId ?>
            </h2>


            <p>
                Complete employee application
                and approval information.
            </p>

        </div>


        <span
            class="leave-status-badge
                   leave-status-<?= strtolower(
                       $record[
                           'status'
                       ]
                   ) ?>
                   leave-status-large"
        >

            <?= escape(
                $record[
                    'status'
                ]
            ) ?>

        </span>

    </div>

</div>


<div class="row g-4">

    <!-- =====================================================
         LEFT COLUMN
         ===================================================== -->

    <div class="col-xl-8">


        <!-- EMPLOYEE -->

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        EMPLOYEE
                    </div>

                    <h5>
                        Employee Information
                    </h5>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-person-vcard"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <div
                    class="admin-record-profile"
                >

                    <div
                        class="admin-record-avatar"
                    >

                        <?= escape(
                            strtoupper(
                                substr(
                                    $record[
                                        'first_name'
                                    ],
                                    0,
                                    1
                                )
                                .
                                substr(
                                    $record[
                                        'last_name'
                                    ],
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <h4>

                            <?= escape(
                                $record[
                                    'first_name'
                                ]
                                . ' '
                                . $record[
                                    'last_name'
                                ]
                            ) ?>

                        </h4>


                        <span>

                            <?= escape(
                                $record[
                                    'employee_code'
                                ]
                            ) ?>

                            ·

                            <?= escape(
                                $record[
                                    'job_title'
                                ]
                            ) ?>

                        </span>


                        <small>

                            <?= escape(
                                $record[
                                    'department_name'
                                ]
                            ) ?>

                        </small>

                    </div>

                </div>


                <div
                    class="admin-record-info-grid
                           mt-4"
                >

                    <div>

                        <span>
                            Email
                        </span>

                        <strong>

                            <?= escape(
                                $record[
                                    'email'
                                ]
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            Phone
                        </span>

                        <strong>

                            <?= !empty(
                                $record[
                                    'phone'
                                ]
                            )
                                ? escape(
                                    $record[
                                        'phone'
                                    ]
                                )
                                : '—'
                            ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            Date Joined
                        </span>

                        <strong>

                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $record[
                                            'date_joined'
                                        ]
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <!-- LEAVE REQUEST -->

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        APPLICATION
                    </div>

                    <h5>

                        <?= escape(
                            $record[
                                'leave_type_name'
                            ]
                        ) ?>

                    </h5>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-calendar2-week"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="leave-view-grid">

                    <div>

                        <span>
                            Start Date
                        </span>

                        <strong>

                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $record[
                                            'start_date'
                                        ]
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            End Date
                        </span>

                        <strong>

                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $record[
                                            'end_date'
                                        ]
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>


                    <div>

                        <span>
                            Duration
                        </span>

                        <strong>

                            <?= number_format(
                                (float)$record[
                                    'number_of_days'
                                ],
                                2
                            ) ?>

                            days

                        </strong>

                    </div>


                    <div>

                        <span>
                            Payment
                        </span>

                        <strong>

                            <?= (int)$record[
                                'is_paid'
                            ] === 1
                                ? 'Paid Leave'
                                : 'Unpaid Leave'
                            ?>

                        </strong>

                    </div>

                </div>


                <div
                    class="leave-reason-box
                           mt-4"
                >

                    <span>
                        Employee Reason
                    </span>

                    <p>

                        <?= nl2br(
                            escape(
                                $record[
                                    'reason'
                                ]
                            )
                        ) ?>

                    </p>

                </div>


                <?php if (
                    !empty(
                        $record[
                            'attachment'
                        ]
                    )
                ): ?>

                    <div
                        class="leave-attachment-card
                               mt-4"
                    >

                        <div>

                            <i
                                class="bi
                                       bi-paperclip"
                            ></i>

                        </div>


                        <span>

                            <strong>
                                Supporting Attachment
                            </strong>

                            <small>
                                Secure employee document
                            </small>

                        </span>


                        <a
                            href="/admin/download-leave-attachment.php?id=<?= $applicationId ?>"
                            class="btn
                                   btn-sm
                                   btn-outline-primary"
                        >

                            <i
                                class="bi
                                       bi-download"
                            ></i>

                            Download

                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- DECISION -->

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        APPROVAL WORKFLOW
                    </div>

                    <h5>
                        Manager Decision
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <?php if (
                    $record[
                        'status'
                    ] === 'Pending'
                ): ?>

                    <div
                        class="admin-pending-decision"
                    >

                        <div>

                            <i
                                class="bi
                                       bi-hourglass-split"
                            ></i>

                        </div>


                        <div>

                            <strong>
                                Awaiting Manager Decision
                            </strong>

                            <p>
                                This application is still
                                pending and has not been
                                approved or rejected.
                            </p>

                        </div>

                    </div>


                <?php else: ?>

                    <div class="leave-decision-panel">

                        <div>

                            <span>
                                Decision
                            </span>

                            <strong>

                                <?= escape(
                                    $record[
                                        'decision'
                                    ]
                                    ??
                                    $record[
                                        'status'
                                    ]
                                ) ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Manager
                            </span>

                            <strong>

                                <?= !empty(
                                    $record[
                                        'manager_name'
                                    ]
                                )
                                    ? escape(
                                        $record[
                                            'manager_name'
                                        ]
                                    )
                                    : '—'
                                ?>

                            </strong>

                        </div>


                        <div>

                            <span>
                                Decision Date
                            </span>

                            <strong>

                                <?= !empty(
                                    $record[
                                        'decision_date'
                                    ]
                                )
                                    ? escape(
                                        date(
                                            'd M Y H:i',
                                            strtotime(
                                                $record[
                                                    'decision_date'
                                                ]
                                            )
                                        )
                                    )
                                    : '—'
                                ?>

                            </strong>

                        </div>

                    </div>


                    <?php if (
                        !empty(
                            $record[
                                'comment'
                            ]
                        )
                    ): ?>

                        <div
                            class="leave-manager-comment
                                   mt-4"
                        >

                            <span>
                                Manager Comment
                            </span>

                            <p>

                                <?= nl2br(
                                    escape(
                                        $record[
                                            'comment'
                                        ]
                                    )
                                ) ?>

                            </p>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         RIGHT COLUMN
         ===================================================== -->

    <div class="col-xl-4">


        <!-- BALANCE -->

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        BALANCE
                    </div>

                    <h5>
                        Leave Balance
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <?php if (
                    $record[
                        'allocated_days'
                    ] !== null
                ): ?>

                    <div
                        class="manager-balance-highlight"
                    >

                        <span>
                            Remaining
                        </span>

                        <strong>

                            <?= number_format(
                                (float)$record[
                                    'remaining_days'
                                ],
                                2
                            ) ?>

                        </strong>

                        <small>
                            days available
                        </small>

                    </div>


                    <div
                        class="balance-detail-grid
                               mt-3"
                    >

                        <div
                            class="balance-detail-item"
                        >

                            <span>
                                Allocated
                            </span>

                            <strong>

                                <?= number_format(
                                    (float)$record[
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
                                    (float)$record[
                                        'used_days'
                                    ],
                                    2
                                ) ?>

                            </strong>

                        </div>

                    </div>


                <?php else: ?>

                    <div
                        class="text-muted small"
                    >
                        No balance record is
                        available for this leave year.
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- POLICY -->

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <h5>
                    Policy Snapshot
                </h5>

            </div>


            <div class="admin-card-body">

                <div class="detail-list">

                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-calendar-check"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Annual Entitlement
                            </span>

                            <strong>

                                <?= $record[
                                    'days_per_year'
                                ] !== null
                                    ? number_format(
                                        (float)$record[
                                            'days_per_year'
                                        ],
                                        2
                                    )
                                    . ' days'
                                    : '—'
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-hourglass"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Minimum Service
                            </span>

                            <strong>

                                <?= $record[
                                    'minimum_service_months'
                                ] !== null
                                    ? (int)$record[
                                        'minimum_service_months'
                                    ]
                                    . ' months'
                                    : '—'
                                ?>

                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-paperclip"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Attachment Rule
                            </span>

                            <strong>

                                <?= (int)(
                                    $record[
                                        'requires_attachment'
                                    ] ?? 0
                                ) === 1
                                    ? 'Required'
                                    : 'Optional'
                                ?>

                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- RECORD METADATA -->

        <div class="admin-card">

            <div class="admin-card-header">

                <h5>
                    Record Information
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
                                Application ID
                            </span>

                            <strong>
                                #<?= $applicationId ?>
                            </strong>

                        </div>

                    </div>


                    <div class="detail-item">

                        <div class="detail-icon">

                            <i
                                class="bi
                                       bi-send"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Applied
                            </span>

                            <strong>

                                <?= escape(
                                    date(
                                        'd M Y H:i',
                                        strtotime(
                                            $record[
                                                'applied_at'
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
                                       bi-arrow-repeat"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Last Updated
                            </span>

                            <strong>

                                <?= escape(
                                    date(
                                        'd M Y H:i',
                                        strtotime(
                                            $record[
                                                'updated_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            </strong>

                        </div>

                    </div>

                </div>


                <div
                    class="admin-info-box
                           mt-4"
                >

                    <div class="admin-info-icon">

                        <i
                            class="bi
                                   bi-shield-lock"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Read-only record
                        </strong>

                        <p>
                            Administrator access here is
                            for centralized record
                            management. Manager approval
                            decisions are not changed
                            from this page.
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