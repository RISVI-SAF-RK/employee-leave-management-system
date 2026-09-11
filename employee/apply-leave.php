<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


$pageTitle = 'Apply for Leave';

$error = '';

$currentYear = (int)date('Y');


/*
|--------------------------------------------------------------------------
| Current Employee
|--------------------------------------------------------------------------
*/

$profileStmt = $pdo->prepare(
    "SELECT
        e.employee_id,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.date_joined,
        e.manager_id,
        e.status,

        d.department_name,

        m.user_id AS manager_user_id,
        m.first_name AS manager_first_name,
        m.last_name AS manager_last_name,
        m.status AS manager_employee_status,

        mu.status AS manager_user_status,

        mr.role_name AS manager_role

     FROM employees e

     INNER JOIN departments d
        ON e.department_id =
           d.department_id

     LEFT JOIN employees m
        ON e.manager_id =
           m.employee_id

     LEFT JOIN users mu
        ON m.user_id =
           mu.user_id

     LEFT JOIN roles mr
        ON mu.role_id =
           mr.role_id

     WHERE e.user_id =
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


$employeeId =
    (int)$employeeProfile[
        'employee_id'
    ];


/*
|--------------------------------------------------------------------------
| Manager Availability
|--------------------------------------------------------------------------
*/

$managerAvailable =
    !empty(
        $employeeProfile[
            'manager_id'
        ]
    )
    &&
    !empty(
        $employeeProfile[
            'manager_user_id'
        ]
    )
    &&
    $employeeProfile[
        'manager_employee_status'
    ] === 'Active'
    &&
    $employeeProfile[
        'manager_user_status'
    ] === 'Active'
    &&
    $employeeProfile[
        'manager_role'
    ] === 'Manager';


/*
|--------------------------------------------------------------------------
| Load Current-Year Leave Types / Policies / Balances
|--------------------------------------------------------------------------
*/

$typeStmt = $pdo->prepare(
    "SELECT
        lt.leave_type_id,
        lt.leave_type_name,
        lt.description,
        lt.is_paid,

        lp.days_per_year,
        lp.minimum_service_months,
        lp.carry_forward_allowed,
        lp.max_carry_forward_days,
        lp.requires_attachment,

        lb.balance_id,
        lb.allocated_days,
        lb.used_days,
        lb.remaining_days,

        COALESCE(
            (
                SELECT
                    SUM(la.number_of_days)

                FROM leave_applications la

                WHERE
                    la.employee_id =
                        :pending_employee_id

                    AND la.leave_type_id =
                        lt.leave_type_id

                    AND la.status =
                        'Pending'

                    AND YEAR(
                        la.start_date
                    ) = :pending_year
            ),
            0
        ) AS pending_days

     FROM leave_types lt

     INNER JOIN leave_policies lp
        ON lt.leave_type_id =
           lp.leave_type_id

     LEFT JOIN leave_balances lb
        ON lb.leave_type_id =
           lt.leave_type_id

        AND lb.employee_id =
           :balance_employee_id

        AND lb.balance_year =
           :balance_year

     WHERE
        lt.status = 'Active'
        AND lp.status = 'Active'

     ORDER BY
        lt.leave_type_name"
);


$typeStmt->execute([

    'pending_employee_id' =>
        $employeeId,

    'pending_year' =>
        $currentYear,

    'balance_employee_id' =>
        $employeeId,

    'balance_year' =>
        $currentYear
]);


$leaveTypes =
    $typeStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Submit Leave Application
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
    | Input
    |--------------------------------------------------------------------------
    */

    $leaveTypeId =
        filter_var(
            $_POST['leave_type_id']
            ?? null,
            FILTER_VALIDATE_INT
        );


    $startDate =
        trim(
            $_POST['start_date']
            ?? ''
        );


    $endDate =
        trim(
            $_POST['end_date']
            ?? ''
        );


    $reason =
        trim(
            $_POST['reason']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (!$leaveTypeId) {

        $error =
            'Please select a leave type.';

    } elseif (
        $startDate === ''
        ||
        $endDate === ''
    ) {

        $error =
            'Please select the start and end dates.';

    } elseif ($reason === '') {

        $error =
            'Please provide a reason for your leave request.';

    } elseif (
        strlen($reason) > 2000
    ) {

        $error =
            'Leave reason cannot exceed 2000 characters.';

    } elseif (!$managerAvailable) {

        $error =
            'You do not currently have an active Manager assigned. Please contact the Administrator.';
    }


    /*
    |--------------------------------------------------------------------------
    | Parse Dates
    |--------------------------------------------------------------------------
    */

    $startDateObject = null;
    $endDateObject = null;

    if ($error === '') {

        try {

            $startDateObject =
                new DateTimeImmutable(
                    $startDate
                );

            $endDateObject =
                new DateTimeImmutable(
                    $endDate
                );


            if (
                $startDateObject
                    ->format('Y-m-d')
                !== $startDate
                ||
                $endDateObject
                    ->format('Y-m-d')
                !== $endDate
            ) {

                throw new RuntimeException();
            }

        } catch (Throwable $e) {

            $error =
                'Please enter valid leave dates.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Date Rules
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        $startDateObject
        &&
        $endDateObject
    ) {

        $today =
            new DateTimeImmutable(
                'today'
            );


        if (
            $startDateObject < $today
        ) {

            $error =
                'The leave start date cannot be in the past.';

        } elseif (
            $endDateObject <
            $startDateObject
        ) {

            $error =
                'The end date cannot be earlier than the start date.';

        } elseif (
            $startDateObject
                ->format('Y')
            !==
            $endDateObject
                ->format('Y')
        ) {

            $error =
                'A leave application cannot cross two leave years. Please submit separate requests for each year.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Number of Days
    |--------------------------------------------------------------------------
    |
    | Current version counts inclusive calendar days.
    | Example: 10 Sep - 12 Sep = 3 days.
    |
    | Weekend/public-holiday exclusions can be added later if the
    | organization introduces a working-calendar policy.
    |--------------------------------------------------------------------------
    */

    $numberOfDays = 0.00;
    $leaveYear = 0;


    if (
        $error === ''
        &&
        $startDateObject
        &&
        $endDateObject
    ) {

        $numberOfDays =
            (float)(
                $startDateObject
                    ->diff(
                        $endDateObject
                    )
                    ->days
                + 1
            );


        $leaveYear =
            (int)$startDateObject
                ->format('Y');
    }


    /*
    |--------------------------------------------------------------------------
    | Load Selected Active Policy
    |--------------------------------------------------------------------------
    */

    $selectedPolicy = null;


    if ($error === '') {

        $policyStmt =
            $pdo->prepare(
                "SELECT
                    lt.leave_type_id,
                    lt.leave_type_name,
                    lt.is_paid,

                    lp.days_per_year,
                    lp.minimum_service_months,
                    lp.carry_forward_allowed,
                    lp.max_carry_forward_days,
                    lp.requires_attachment

                 FROM leave_types lt

                 INNER JOIN leave_policies lp
                    ON lt.leave_type_id =
                       lp.leave_type_id

                 WHERE
                    lt.leave_type_id =
                        :leave_type_id

                    AND lt.status =
                        'Active'

                    AND lp.status =
                        'Active'

                 LIMIT 1"
            );


        $policyStmt->execute([
            'leave_type_id' =>
                $leaveTypeId
        ]);


        $selectedPolicy =
            $policyStmt->fetch();


        if (!$selectedPolicy) {

            $error =
                'The selected leave type does not have an active policy.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Minimum Service Eligibility
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        $selectedPolicy
    ) {

        $minimumMonths =
            (int)$selectedPolicy[
                'minimum_service_months'
            ];


        $joinedDate =
            new DateTimeImmutable(
                $employeeProfile[
                    'date_joined'
                ]
            );


        $eligibleDate =
            $joinedDate->modify(
                '+'
                . $minimumMonths
                . ' months'
            );


        if (
            $startDateObject <
            $eligibleDate
        ) {

            $error =
                'You are not yet eligible for '
                . $selectedPolicy[
                    'leave_type_name'
                ]
                . '. Minimum service requirement: '
                . $minimumMonths
                . ' month(s).';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Find Leave Balance
    |--------------------------------------------------------------------------
    */

    $balance = null;
    $pendingDays = 0.00;


    if (
        $error === ''
        &&
        $selectedPolicy
    ) {

        $balanceStmt =
            $pdo->prepare(
                "SELECT
                    balance_id,
                    allocated_days,
                    used_days,
                    remaining_days

                 FROM leave_balances

                 WHERE
                    employee_id =
                        :employee_id

                    AND leave_type_id =
                        :leave_type_id

                    AND balance_year =
                        :balance_year

                 LIMIT 1"
            );


        $balanceStmt->execute([

            'employee_id' =>
                $employeeId,

            'leave_type_id' =>
                $leaveTypeId,

            'balance_year' =>
                $leaveYear
        ]);


        $balance =
            $balanceStmt->fetch();


        if (!$balance) {

            $error =
                'Your leave balance has not been initialized for '
                . $leaveYear
                . '. Please contact the Administrator.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Pending Leave Reservation
    |--------------------------------------------------------------------------
    */

    if (
        $error === ''
        &&
        $balance
    ) {

        $pendingStmt =
            $pdo->prepare(
                "SELECT
                    COALESCE(
                        SUM(number_of_days),
                        0
                    )

                 FROM leave_applications

                 WHERE
                    employee_id =
                        :employee_id

                    AND leave_type_id =
                        :leave_type_id

                    AND status =
                        'Pending'

                    AND YEAR(
                        start_date
                    ) = :leave_year"
            );


        $pendingStmt->execute([

            'employee_id' =>
                $employeeId,

            'leave_type_id' =>
                $leaveTypeId,

            'leave_year' =>
                $leaveYear
        ]);


        $pendingDays =
            (float)$pendingStmt
                ->fetchColumn();


        $availableToRequest =
            max(
                0,
                (float)$balance[
                    'remaining_days'
                ]
                -
                $pendingDays
            );


        if (
            $numberOfDays >
            $availableToRequest
        ) {

            $error =
                'Insufficient available leave balance. '
                . 'You can currently request up to '
                . number_format(
                    $availableToRequest,
                    2
                )
                . ' day(s) after considering pending requests.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Overlapping Leave Check
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $overlapStmt =
            $pdo->prepare(
                "SELECT
                    application_id

                 FROM leave_applications

                 WHERE
                    employee_id =
                        :employee_id

                    AND status IN (
                        'Pending',
                        'Approved'
                    )

                    AND start_date <=
                        :new_end_date

                    AND end_date >=
                        :new_start_date

                 LIMIT 1"
            );


        $overlapStmt->execute([

            'employee_id' =>
                $employeeId,

            'new_end_date' =>
                $endDate,

            'new_start_date' =>
                $startDate
        ]);


        if (
            $overlapStmt->fetch()
        ) {

            $error =
                'You already have a pending or approved leave request that overlaps these dates.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Attachment Validation
    |--------------------------------------------------------------------------
    */

    $requiresAttachment =
        $selectedPolicy
            ? (
                (int)$selectedPolicy[
                    'requires_attachment'
                ] === 1
            )
            : false;


    $fileProvided =
        isset(
            $_FILES['attachment']
        )
        &&
        (
            $_FILES['attachment']['error']
            ?? UPLOAD_ERR_NO_FILE
        )
        !== UPLOAD_ERR_NO_FILE;


    if (
        $error === ''
        &&
        $requiresAttachment
        &&
        !$fileProvided
    ) {

        $error =
            'A supporting attachment is required for this leave type.';
    }


    /*
    |--------------------------------------------------------------------------
    | Process Attachment
    |--------------------------------------------------------------------------
    */

    $storedAttachment = null;
    $storedAttachmentPath = null;


    if (
        $error === ''
        &&
        $fileProvided
    ) {

        $upload =
            $_FILES['attachment'];


        if (
            $upload['error']
            !== UPLOAD_ERR_OK
        ) {

            $error =
                'The attachment could not be uploaded.';

        } elseif (
            $upload['size']
            > 5 * 1024 * 1024
        ) {

            $error =
                'Attachment size cannot exceed 5 MB.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Validate MIME Type
            |--------------------------------------------------------------------------
            */

            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mimeType =
                $finfo->file(
                    $upload['tmp_name']
                );


            $allowedTypes = [

                'application/pdf'
                    => 'pdf',

                'image/jpeg'
                    => 'jpg',

                'image/png'
                    => 'png'
            ];


            if (
                !isset(
                    $allowedTypes[
                        $mimeType
                    ]
                )
            ) {

                $error =
                    'Only PDF, JPG and PNG attachments are allowed.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Railway Persistent Volume
                |--------------------------------------------------------------------------
                */

                $volumePath =
                    getenv(
                        'RAILWAY_VOLUME_MOUNT_PATH'
                    );


                if (!$volumePath) {

                    $error =
                        'Persistent attachment storage is not configured. Please contact the Administrator.';

                } else {

                    $attachmentDirectory =
                        rtrim(
                            $volumePath,
                            '/'
                        )
                        . '/leave-attachments';


                    if (
                        !is_dir(
                            $attachmentDirectory
                        )
                        &&
                        !mkdir(
                            $attachmentDirectory,
                            0750,
                            true
                        )
                    ) {

                        $error =
                            'Unable to prepare secure attachment storage.';

                    } else {

                        $extension =
                            $allowedTypes[
                                $mimeType
                            ];


                        $storedAttachment =
                            bin2hex(
                                random_bytes(20)
                            )
                            . '.'
                            . $extension;


                        $storedAttachmentPath =
                            $attachmentDirectory
                            . '/'
                            . $storedAttachment;


                        if (
                            !move_uploaded_file(
                                $upload[
                                    'tmp_name'
                                ],
                                $storedAttachmentPath
                            )
                        ) {

                            $error =
                                'Unable to securely save the attachment.';
                        }
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Application + Manager Notification
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            $applicationStmt =
                $pdo->prepare(
                    "INSERT INTO leave_applications
                    (
                        employee_id,
                        leave_type_id,
                        start_date,
                        end_date,
                        number_of_days,
                        reason,
                        attachment,
                        status
                    )

                    VALUES
                    (
                        :employee_id,
                        :leave_type_id,
                        :start_date,
                        :end_date,
                        :number_of_days,
                        :reason,
                        :attachment,
                        'Pending'
                    )"
                );


            $applicationStmt->execute([

                'employee_id' =>
                    $employeeId,

                'leave_type_id' =>
                    $leaveTypeId,

                'start_date' =>
                    $startDate,

                'end_date' =>
                    $endDate,

                'number_of_days' =>
                    $numberOfDays,

                'reason' =>
                    $reason,

                'attachment' =>
                    $storedAttachment
            ]);


            $applicationId =
                (int)$pdo
                    ->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Notify Manager
            |--------------------------------------------------------------------------
            */

            $employeeFullName =
                $employeeProfile[
                    'first_name'
                ]
                . ' '
                . $employeeProfile[
                    'last_name'
                ];


            $notificationTitle =
                'New Leave Request';


            $notificationMessage =
                $employeeFullName
                . ' submitted '
                . number_format(
                    $numberOfDays,
                    2
                )
                . ' day(s) of '
                . $selectedPolicy[
                    'leave_type_name'
                ]
                . ' from '
                . date(
                    'd M Y',
                    strtotime(
                        $startDate
                    )
                )
                . ' to '
                . date(
                    'd M Y',
                    strtotime(
                        $endDate
                    )
                )
                . '.';


            $notificationStmt =
                $pdo->prepare(
                    "INSERT INTO notifications
                    (
                        user_id,
                        title,
                        message,
                        is_read
                    )

                    VALUES
                    (
                        :user_id,
                        :title,
                        :message,
                        FALSE
                    )"
                );


            $notificationStmt->execute([

                'user_id' =>
                    (int)$employeeProfile[
                        'manager_user_id'
                    ],

                'title' =>
                    $notificationTitle,

                'message' =>
                    $notificationMessage
            ]);


            $pdo->commit();


            setFlash(
                'success',
                'Your leave application was submitted successfully and sent to your Manager.'
            );


            header(
                'Location: /employee/view-leave.php?id='
                . $applicationId
            );

            exit;


        } catch (Throwable $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Remove uploaded file when DB transaction fails
            |--------------------------------------------------------------------------
            */

            if (
                $storedAttachmentPath
                &&
                is_file(
                    $storedAttachmentPath
                )
            ) {

                @unlink(
                    $storedAttachmentPath
                );
            }


            error_log(
                'Submit leave error: '
                . $e->getMessage()
            );


            $error =
                'Unable to submit your leave application. Please try again.';
        }
    }
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

        <span>
            Apply for Leave
        </span>

    </div>


    <h2>
        Apply for Leave
    </h2>


    <p>
        Submit a leave request for
        Manager review and approval.
    </p>

</div>


<?php if (!$managerAvailable): ?>

    <div class="alert alert-warning">

        <i
            class="bi
                   bi-exclamation-triangle-fill
                   me-2"
        ></i>

        An active Manager is not assigned
        to your profile. You cannot submit
        leave until an Administrator assigns
        one.

    </div>

<?php endif; ?>


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
         APPLICATION FORM
         ===================================================== -->

    <div class="col-xl-8">

        <form
            method="POST"
            enctype="multipart/form-data"
            id="leaveApplicationForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(
                    generateCsrfToken()
                ) ?>"
            >


            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <div
                            class="employee-section-label"
                        >
                            STEP 1
                        </div>

                        <h5>
                            Select Leave Type
                        </h5>

                        <small class="text-muted">
                            Choose an active leave
                            category.
                        </small>

                    </div>


                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-calendar-event"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <label
                        for="leave_type_id"
                        class="professional-form-label"
                    >
                        Leave Type

                        <span class="required-mark">
                            *
                        </span>
                    </label>


                    <select
                        id="leave_type_id"
                        name="leave_type_id"
                        class="form-select
                               professional-input"
                        required
                    >

                        <option value="">
                            Select Leave Type
                        </option>


                        <?php foreach (
                            $leaveTypes
                            as $type
                        ): ?>

                            <?php

                            $remaining =
                                isset(
                                    $type[
                                        'remaining_days'
                                    ]
                                )
                                    ? (float)$type[
                                        'remaining_days'
                                    ]
                                    : 0;


                            $pending =
                                (float)$type[
                                    'pending_days'
                                ];


                            $available =
                                max(
                                    0,
                                    $remaining
                                    -
                                    $pending
                                );

                            ?>


                            <option
                                value="<?= (int)$type[
                                    'leave_type_id'
                                ] ?>"

                                data-paid="<?= (int)$type[
                                    'is_paid'
                                ] ?>"

                                data-remaining="<?= number_format(
                                    $remaining,
                                    2,
                                    '.',
                                    ''
                                ) ?>"

                                data-pending="<?= number_format(
                                    $pending,
                                    2,
                                    '.',
                                    ''
                                ) ?>"

                                data-available="<?= number_format(
                                    $available,
                                    2,
                                    '.',
                                    ''
                                ) ?>"

                                data-min-service="<?= (int)$type[
                                    'minimum_service_months'
                                ] ?>"

                                data-attachment="<?= (int)$type[
                                    'requires_attachment'
                                ] ?>"

                                <?= (
                                    ($_POST[
                                        'leave_type_id'
                                    ] ?? '')
                                    ==
                                    $type[
                                        'leave_type_id'
                                    ]
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= escape(
                                    $type[
                                        'leave_type_name'
                                    ]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <div
                        id="leavePolicyPreview"
                        class="leave-policy-preview
                               mt-4
                               d-none"
                    >

                        <div>

                            <span>
                                Available
                            </span>

                            <strong
                                id="previewAvailable"
                            >
                                0.00
                            </strong>

                            <small>
                                days
                            </small>

                        </div>


                        <div>

                            <span>
                                Pending
                            </span>

                            <strong
                                id="previewPending"
                            >
                                0.00
                            </strong>

                            <small>
                                days
                            </small>

                        </div>


                        <div>

                            <span>
                                Minimum Service
                            </span>

                            <strong
                                id="previewService"
                            >
                                0
                            </strong>

                            <small>
                                months
                            </small>

                        </div>


                        <div>

                            <span>
                                Attachment
                            </span>

                            <strong
                                id="previewAttachment"
                            >
                                Optional
                            </strong>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 DATES
                 ================================================= -->

            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <div
                            class="employee-section-label"
                        >
                            STEP 2
                        </div>

                        <h5>
                            Leave Dates
                        </h5>

                        <small class="text-muted">
                            Choose the requested
                            leave period.
                        </small>

                    </div>


                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-calendar-range"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="row g-4">

                        <div class="col-md-6">

                            <label
                                for="start_date"
                                class="professional-form-label"
                            >
                                Start Date

                                <span class="required-mark">
                                    *
                                </span>
                            </label>


                            <input
                                type="date"
                                id="start_date"
                                name="start_date"
                                min="<?= date(
                                    'Y-m-d'
                                ) ?>"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $_POST[
                                        'start_date'
                                    ] ?? ''
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                for="end_date"
                                class="professional-form-label"
                            >
                                End Date

                                <span class="required-mark">
                                    *
                                </span>
                            </label>


                            <input
                                type="date"
                                id="end_date"
                                name="end_date"
                                min="<?= date(
                                    'Y-m-d'
                                ) ?>"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $_POST[
                                        'end_date'
                                    ] ?? ''
                                ) ?>"
                            >

                        </div>

                    </div>


                    <div
                        class="leave-duration-preview"
                    >

                        <div
                            class="leave-duration-icon"
                        >

                            <i
                                class="bi
                                       bi-hourglass-split"
                            ></i>

                        </div>


                        <div>

                            <span>
                                Requested Duration
                            </span>

                            <strong
                                id="requestedDays"
                            >
                                Select dates
                            </strong>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 REASON
                 ================================================= -->

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <div
                            class="employee-section-label"
                        >
                            STEP 3
                        </div>

                        <h5>
                            Request Details
                        </h5>

                        <small class="text-muted">
                            Provide supporting
                            information.
                        </small>

                    </div>


                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-file-earmark-text"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="mb-4">

                        <label
                            for="reason"
                            class="professional-form-label"
                        >
                            Reason

                            <span class="required-mark">
                                *
                            </span>
                        </label>


                        <textarea
                            id="reason"
                            name="reason"
                            maxlength="2000"
                            rows="5"
                            required
                            class="form-control
                                   professional-input"
                            placeholder="Briefly explain the reason for your leave request..."
                        ><?= escape(
                            $_POST[
                                'reason'
                            ] ?? ''
                        ) ?></textarea>

                    </div>


                    <div class="mb-2">

                        <label
                            for="attachment"
                            class="professional-form-label"
                        >
                            Supporting Attachment

                            <span
                                id="attachmentRequiredMark"
                                class="required-mark
                                       d-none"
                            >
                                *
                            </span>
                        </label>


                        <div class="leave-upload-box">

                            <i
                                class="bi
                                       bi-cloud-arrow-up"
                            ></i>


                            <div>

                                <strong>
                                    Upload supporting document
                                </strong>

                                <span>
                                    PDF, JPG or PNG · Maximum 5 MB
                                </span>

                            </div>


                            <input
                                type="file"
                                id="attachment"
                                name="attachment"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                class="form-control"
                            >

                        </div>

                    </div>


                    <div class="form-actions">

                        <a
                            href="/employee/dashboard.php"
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
                            <?= !$managerAvailable
                                ? 'disabled'
                                : ''
                            ?>
                        >

                            <i
                                class="bi
                                       bi-send-fill"
                            ></i>

                            Submit Application

                        </button>

                    </div>

                </div>

            </div>

        </form>

    </div>


    <!-- =====================================================
         RIGHT SIDE
         ===================================================== -->

    <div class="col-xl-4">

        <div
            class="admin-card
                   mb-4"
        >

            <div class="admin-card-header">

                <div>

                    <div
                        class="employee-section-label"
                    >
                        APPROVAL
                    </div>

                    <h5>
                        Reporting Manager
                    </h5>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-person-check-fill"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <?php if (
                    $managerAvailable
                ): ?>

                    <div
                        class="leave-manager-card"
                    >

                        <div
                            class="leave-manager-avatar"
                        >

                            <?= escape(
                                strtoupper(
                                    substr(
                                        $employeeProfile[
                                            'manager_first_name'
                                        ],
                                        0,
                                        1
                                    )
                                    .
                                    substr(
                                        $employeeProfile[
                                            'manager_last_name'
                                        ],
                                        0,
                                        1
                                    )
                                )
                            ) ?>

                        </div>


                        <div>

                            <strong>

                                <?= escape(
                                    $employeeProfile[
                                        'manager_first_name'
                                    ]
                                    . ' '
                                    . $employeeProfile[
                                        'manager_last_name'
                                    ]
                                ) ?>

                            </strong>

                            <span>
                                Your leave request will
                                be sent here for review.
                            </span>

                        </div>

                    </div>


                <?php else: ?>

                    <div
                        class="text-muted small"
                    >
                        No active Manager assigned.
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <div class="admin-info-box">

            <div class="admin-info-icon">

                <i
                    class="bi
                           bi-shield-check"
                ></i>

            </div>


            <div>

                <strong>
                    Before you submit
                </strong>

                <p>
                    ELMS validates your policy,
                    service eligibility, available
                    balance, overlapping requests
                    and attachment requirements
                    before creating the application.
                </p>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';