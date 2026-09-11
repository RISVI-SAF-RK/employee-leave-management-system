<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


$pageTitle =
    'Review Leave Request';


$applicationId =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


if (!$applicationId) {

    setFlash(
        'danger',
        'Invalid leave request.'
    );

    header(
        'Location: /manager/pending-requests.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Manager
|--------------------------------------------------------------------------
*/

$managerStmt =
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


$managerStmt->execute([
    'user_id' =>
        (int)$_SESSION['user_id']
]);


$managerProfile =
    $managerStmt->fetch();


if (!$managerProfile) {

    http_response_code(403);

    exit(
        'Manager profile not found.'
    );
}


$managerId =
    (int)$managerProfile[
        'employee_id'
    ];


/*
|--------------------------------------------------------------------------
| Request
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

            e.user_id AS employee_user_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.job_title,
            e.date_joined,

            d.department_name,

            lt.leave_type_name,
            lt.description,
            lt.is_paid,

            lp.minimum_service_months,
            lp.requires_attachment,

            lb.allocated_days,
            lb.used_days,
            lb.remaining_days,

            lap.manager_id AS approval_manager_id,
            lap.decision,
            lap.comment,
            lap.decision_date

         FROM leave_applications la

         INNER JOIN employees e
            ON la.employee_id =
               e.employee_id

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         INNER JOIN leave_types lt
            ON la.leave_type_id =
               lt.leave_type_id

         LEFT JOIN leave_policies lp
            ON lt.leave_type_id =
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

         WHERE
            la.application_id =
                :application_id

            AND
            (
                e.manager_id =
                    :current_manager_id

                OR lap.manager_id =
                    :approval_manager_id
            )

         LIMIT 1"
    );


$stmt->execute([

    'application_id' =>
        $applicationId,

    'current_manager_id' =>
        $managerId,

    'approval_manager_id' =>
        $managerId
]);


$request =
    $stmt->fetch();


if (!$request) {

    http_response_code(404);

    exit(
        'Leave request not found or access denied.'
    );
}


require_once __DIR__
    . '/../includes/manager/header.php';
?>


<div class="page-heading">

    <div class="page-breadcrumb">

        <a href="/manager/dashboard.php">
            Dashboard
        </a>

        <i class="bi bi-chevron-right"></i>

        <a href="/manager/pending-requests.php">
            Leave Requests
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
                Review Leave Request
            </h2>

            <p>
                Review employee information,
                leave balance and request details.
            </p>

        </div>


        <span
            class="leave-status-badge
                   leave-status-<?= strtolower(
                       $request[
                           'status'
                       ]
                   ) ?>
                   leave-status-large"
        >

            <?= escape(
                $request[
                    'status'
                ]
            ) ?>

        </span>

    </div>

</div>


<div class="row g-4">

    <!-- LEFT -->

    <div class="col-xl-8">

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        EMPLOYEE
                    </div>

                    <h5>
                        Employee Information
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="manager-employee-profile">

                    <div class="manager-review-avatar">

                        <?= escape(
                            strtoupper(
                                substr(
                                    $request[
                                        'first_name'
                                    ],
                                    0,
                                    1
                                )
                                .
                                substr(
                                    $request[
                                        'last_name'
                                    ],
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <h5>

                            <?= escape(
                                $request[
                                    'first_name'
                                ]
                                . ' '
                                . $request[
                                    'last_name'
                                ]
                            ) ?>

                        </h5>


                        <span>

                            <?= escape(
                                $request[
                                    'employee_code'
                                ]
                            ) ?>

                            ·

                            <?= escape(
                                $request[
                                    'job_title'
                                ]
                            ) ?>

                        </span>


                        <small>

                            <?= escape(
                                $request[
                                    'department_name'
                                ]
                            ) ?>

                        </small>

                    </div>

                </div>

            </div>

        </div>


        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        REQUEST
                    </div>

                    <h5>

                        <?= escape(
                            $request[
                                'leave_type_name'
                            ]
                        ) ?>

                    </h5>

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
                                        $request[
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
                                        $request[
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
                                (float)$request[
                                    'number_of_days'
                                ],
                                2
                            ) ?>

                            days

                        </strong>

                    </div>


                    <div>

                        <span>
                            Submitted
                        </span>

                        <strong>

                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $request[
                                            'applied_at'
                                        ]
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div class="leave-reason-box mt-4">

                    <span>
                        Reason
                    </span>

                    <p>

                        <?= nl2br(
                            escape(
                                $request[
                                    'reason'
                                ]
                            )
                        ) ?>

                    </p>

                </div>


                <?php if (
                    !empty(
                        $request[
                            'attachment'
                        ]
                    )
                ): ?>

                    <div class="leave-attachment-card mt-4">

                        <div>

                            <i class="bi bi-paperclip"></i>

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
                            href="/manager/download-attachment.php?id=<?= $applicationId ?>"
                            class="btn
                                   btn-sm
                                   btn-outline-primary"
                        >

                            <i class="bi bi-download"></i>

                            Download

                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <?php if (
            $request['status']
            === 'Pending'
        ): ?>

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <div class="employee-section-label">
                            DECISION
                        </div>

                        <h5>
                            Manager Decision
                        </h5>

                        <small class="text-muted">
                            Approve or reject this
                            leave request.
                        </small>

                    </div>

                </div>


                <div class="admin-card-body">

                    <form
                        method="POST"
                        action="/manager/process-request.php"
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
                            name="application_id"
                            value="<?= $applicationId ?>"
                        >


                        <div class="mb-4">

                            <label
                                for="comment"
                                class="professional-form-label"
                            >
                                Manager Comment
                            </label>


                            <textarea
                                id="comment"
                                name="comment"
                                rows="4"
                                maxlength="2000"
                                class="form-control
                                       professional-input"
                                placeholder="Add an optional approval comment or provide a reason for rejection..."
                            ></textarea>


                            <div class="form-help">
                                A comment is required
                                when rejecting a request.
                            </div>

                        </div>


                        <div class="manager-decision-actions">

                            <button
                                type="submit"
                                name="decision"
                                value="Rejected"
                                class="manager-reject-button"
                                onclick="return confirm(
                                    'Reject this leave request?'
                                );"
                            >

                                <i class="bi bi-x-circle-fill"></i>

                                Reject Request

                            </button>


                            <button
                                type="submit"
                                name="decision"
                                value="Approved"
                                class="manager-approve-button"
                                onclick="return confirm(
                                    'Approve this leave request?'
                                );"
                            >

                                <i class="bi bi-check-circle-fill"></i>

                                Approve Request

                            </button>

                        </div>

                    </form>

                </div>

            </div>


        <?php else: ?>

            <div class="admin-card">

                <div class="admin-card-header">

                    <h5>
                        Decision
                    </h5>

                </div>


                <div class="admin-card-body">

                    <div class="leave-decision-panel">

                        <div>

                            <span>
                                Decision
                            </span>

                            <strong>
                                <?= escape(
                                    $request[
                                        'decision'
                                    ]
                                    ??
                                    $request[
                                        'status'
                                    ]
                                ) ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Decision Date
                            </span>

                            <strong>

                                <?= !empty(
                                    $request[
                                        'decision_date'
                                    ]
                                )
                                    ? escape(
                                        date(
                                            'd M Y H:i',
                                            strtotime(
                                                $request[
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
                            $request['comment']
                        )
                    ): ?>

                        <div class="leave-manager-comment mt-4">

                            <span>
                                Comment
                            </span>

                            <p>

                                <?= nl2br(
                                    escape(
                                        $request[
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


    <!-- RIGHT -->

    <div class="col-xl-4">

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        LEAVE BALANCE
                    </div>

                    <h5>
                        Current Balance
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="manager-balance-highlight">

                    <span>
                        Available
                    </span>

                    <strong>

                        <?= number_format(
                            (float)(
                                $request[
                                    'remaining_days'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>

                    </strong>

                    <small>
                        days remaining
                    </small>

                </div>


                <div class="balance-detail-grid mt-3">

                    <div class="balance-detail-item">

                        <span>
                            Allocated
                        </span>

                        <strong>

                            <?= number_format(
                                (float)(
                                    $request[
                                        'allocated_days'
                                    ]
                                    ?? 0
                                ),
                                2
                            ) ?>

                        </strong>

                    </div>


                    <div class="balance-detail-item">

                        <span>
                            Used
                        </span>

                        <strong>

                            <?= number_format(
                                (float)(
                                    $request[
                                        'used_days'
                                    ]
                                    ?? 0
                                ),
                                2
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div class="admin-info-box mt-4">

                    <div class="admin-info-icon">

                        <i class="bi bi-shield-check"></i>

                    </div>


                    <div>

                        <strong>
                            Balance protection
                        </strong>

                        <p>
                            The balance changes only
                            if you approve this request.
                            Rejection leaves the balance
                            unchanged.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/manager/footer.php';