<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


$pageTitle =
    'Leave Application';


$applicationId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (!$applicationId) {

    setFlash(
        'danger',
        'Invalid leave application.'
    );

    header(
        'Location: /employee/leave-history.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current Employee
|--------------------------------------------------------------------------
*/

$profileStmt =
    $pdo->prepare(
        "SELECT
            employee_id,
            first_name,
            last_name

         FROM employees

         WHERE user_id =
            :user_id

         LIMIT 1"
    );


$profileStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$employeeProfile =
    $profileStmt->fetch();


if (!$employeeProfile) {

    http_response_code(403);

    exit(
        'Employee profile not found.'
    );
}


/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
|
| IMPORTANT:
| employee_id restriction prevents employees from viewing another
| employee's application by changing ?id= in the URL.
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare(
        "SELECT
            la.application_id,
            la.start_date,
            la.end_date,
            la.number_of_days,
            la.reason,
            la.attachment,
            la.status,
            la.applied_at,
            la.updated_at,

            lt.leave_type_name,
            lt.description,
            lt.is_paid,

            lap.decision,
            lap.comment,
            lap.decision_date,

            CONCAT(
                m.first_name,
                ' ',
                m.last_name
            ) AS manager_name

         FROM leave_applications la

         INNER JOIN leave_types lt
            ON la.leave_type_id =
               lt.leave_type_id

         LEFT JOIN leave_approvals lap
            ON la.application_id =
               lap.application_id

         LEFT JOIN employees m
            ON lap.manager_id =
               m.employee_id

         WHERE
            la.application_id =
                :application_id

            AND la.employee_id =
                :employee_id

         LIMIT 1"
    );


$stmt->execute([

    'application_id' =>
        $applicationId,

    'employee_id' =>
        (int)$employeeProfile[
            'employee_id'
        ]
]);


$application =
    $stmt->fetch();


if (!$application) {

    http_response_code(404);

    exit(
        'Leave application not found.'
    );
}


require_once __DIR__
    . '/../includes/employee/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/employee/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <a href="/employee/leave-history.php">
            Leave History
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
                Leave Application
                #<?= $applicationId ?>
            </h2>

            <p>
                Review your submitted
                leave request and current
                decision status.
            </p>

        </div>


        <span
            class="leave-status-badge
                   leave-status-<?= strtolower(
                       $application[
                           'status'
                       ]
                   ) ?>
                   leave-status-large"
        >

            <?= escape(
                $application[
                    'status'
                ]
            ) ?>

        </span>

    </div>

</div>


<div class="row g-4">

    <div class="col-xl-8">

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        REQUEST DETAILS
                    </div>

                    <h5>
                        <?= escape(
                            $application[
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

                <div
                    class="leave-view-grid"
                >

                    <div>

                        <span>
                            Start Date
                        </span>

                        <strong>
                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $application[
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
                                        $application[
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
                                (float)$application[
                                    'number_of_days'
                                ],
                                2
                            ) ?>
                            days
                        </strong>

                    </div>


                    <div>

                        <span>
                            Payment Type
                        </span>

                        <strong>
                            <?= (int)$application[
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
                        Reason
                    </span>

                    <p>
                        <?= nl2br(
                            escape(
                                $application[
                                    'reason'
                                ]
                            )
                        ) ?>
                    </p>

                </div>


                <?php if (
                    !empty(
                        $application[
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
                                Secure uploaded document
                            </small>

                        </span>


                        <a
                            href="/employee/download-attachment.php?id=<?= $applicationId ?>"
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


        <?php if (
            $application['status']
            !== 'Pending'
        ): ?>

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <div
                            class="employee-section-label"
                        >
                            MANAGER DECISION
                        </div>

                        <h5>
                            Application Decision
                        </h5>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div
                        class="leave-decision-panel"
                    >

                        <div>

                            <span>
                                Decision
                            </span>

                            <strong>
                                <?= escape(
                                    $application[
                                        'decision'
                                    ]
                                    ??
                                    $application[
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
                                <?= escape(
                                    $application[
                                        'manager_name'
                                    ]
                                    ?? 'Manager'
                                ) ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Decision Date
                            </span>

                            <strong>

                                <?= !empty(
                                    $application[
                                        'decision_date'
                                    ]
                                )
                                    ? escape(
                                        date(
                                            'd M Y H:i',
                                            strtotime(
                                                $application[
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
                            $application[
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
                                        $application[
                                            'comment'
                                        ]
                                    )
                                ) ?>
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <div class="col-xl-4">

        <div class="admin-card">

            <div class="admin-card-header">

                <h5>
                    Application Timeline
                </h5>

            </div>


            <div class="admin-card-body">

                <div
                    class="leave-timeline"
                >

                    <div
                        class="leave-timeline-item
                               completed"
                    >

                        <span></span>

                        <div>

                            <strong>
                                Application Submitted
                            </strong>

                            <small>
                                <?= escape(
                                    date(
                                        'd M Y H:i',
                                        strtotime(
                                            $application[
                                                'applied_at'
                                            ]
                                        )
                                    )
                                ) ?>
                            </small>

                        </div>

                    </div>


                    <div
                        class="leave-timeline-item
                        <?= $application[
                            'status'
                        ] === 'Pending'
                            ? 'current'
                            : 'completed'
                        ?>"
                    >

                        <span></span>

                        <div>

                            <strong>
                                Manager Review
                            </strong>

                            <small>

                                <?= $application[
                                    'status'
                                ] === 'Pending'
                                    ? 'Waiting for decision'
                                    : 'Review completed'
                                ?>

                            </small>

                        </div>

                    </div>


                    <?php if (
                        $application[
                            'status'
                        ] !== 'Pending'
                    ): ?>

                        <div
                            class="leave-timeline-item
                                   <?= $application[
                                       'status'
                                   ] === 'Approved'
                                       ? 'approved'
                                       : 'rejected'
                                   ?>"
                        >

                            <span></span>

                            <div>

                                <strong>
                                    <?= escape(
                                        $application[
                                            'status'
                                        ]
                                    ) ?>
                                </strong>

                                <small>
                                    Final decision
                                </small>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';