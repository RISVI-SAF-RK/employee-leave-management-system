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
    'My Profile';


/*
|--------------------------------------------------------------------------
| Load Current Employee Profile
|--------------------------------------------------------------------------
*/

$profileStmt =
    $pdo->prepare(
        "SELECT
            e.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.phone,
            e.job_title,
            e.date_joined,
            e.status AS employee_status,

            u.email,
            u.status AS user_status,

            d.department_name,

            CONCAT(
                m.first_name,
                ' ',
                m.last_name
            ) AS manager_name

         FROM employees e

         INNER JOIN users u
            ON e.user_id =
               u.user_id

         INNER JOIN departments d
            ON e.department_id =
               d.department_id

         LEFT JOIN employees m
            ON e.manager_id =
               m.employee_id

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
            My Profile
        </span>

    </div>


    <h2>
        My Profile
    </h2>


    <p>
        View your personal and employment information.
    </p>

</div>


<div class="row g-4">

    <div class="col-xl-8">

        <div class="admin-card">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        PROFILE
                    </div>

                    <h5>
                        Personal Information
                    </h5>

                    <small class="text-muted">
                        Your registered ELMS account details
                    </small>

                </div>


                <div class="header-icon-box">

                    <i
                        class="bi
                               bi-person-vcard-fill"
                    ></i>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="row g-4">

                    <div class="col-md-6">

                        <div class="premium-profile-item">

                            <div class="premium-profile-icon">
                                <i class="bi bi-person"></i>
                            </div>

                            <div>

                                <span>
                                    Full Name
                                </span>

                                <strong>
                                    <?= escape(
                                        trim(
                                            $employeeProfile[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $employeeProfile[
                                                'last_name'
                                            ]
                                        )
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="premium-profile-item">

                            <div class="premium-profile-icon">
                                <i class="bi bi-envelope"></i>
                            </div>

                            <div>

                                <span>
                                    Email Address
                                </span>

                                <strong>
                                    <?= escape(
                                        $employeeProfile[
                                            'email'
                                        ]
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="premium-profile-item">

                            <div class="premium-profile-icon">
                                <i class="bi bi-telephone"></i>
                            </div>

                            <div>

                                <span>
                                    Phone Number
                                </span>

                                <strong>
                                    <?= !empty(
                                        $employeeProfile[
                                            'phone'
                                        ]
                                    )
                                        ? escape(
                                            $employeeProfile[
                                                'phone'
                                            ]
                                        )
                                        : 'Not Provided'
                                    ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="premium-profile-item">

                            <div class="premium-profile-icon">
                                <i class="bi bi-person-badge"></i>
                            </div>

                            <div>

                                <span>
                                    Employee Code
                                </span>

                                <strong>
                                    <?= escape(
                                        $employeeProfile[
                                            'employee_code'
                                        ]
                                    ) ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-xl-4">

        <div class="admin-card mb-4">

            <div class="admin-card-header">

                <div>

                    <div class="employee-section-label">
                        EMPLOYMENT
                    </div>

                    <h5>
                        Work Details
                    </h5>

                </div>

            </div>


            <div class="admin-card-body">

                <div class="premium-profile-list">

                    <div class="premium-profile-item">

                        <div class="premium-profile-icon">
                            <i class="bi bi-building"></i>
                        </div>

                        <div>

                            <span>
                                Department
                            </span>

                            <strong>
                                <?= escape(
                                    $employeeProfile[
                                        'department_name'
                                    ]
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="premium-profile-item">

                        <div class="premium-profile-icon">
                            <i class="bi bi-briefcase"></i>
                        </div>

                        <div>

                            <span>
                                Job Title
                            </span>

                            <strong>
                                <?= escape(
                                    $employeeProfile[
                                        'job_title'
                                    ]
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="premium-profile-item">

                        <div class="premium-profile-icon">
                            <i class="bi bi-person-check"></i>
                        </div>

                        <div>

                            <span>
                                Reporting Manager
                            </span>

                            <strong>
                                <?= !empty(
                                    $employeeProfile[
                                        'manager_name'
                                    ]
                                )
                                    ? escape(
                                        $employeeProfile[
                                            'manager_name'
                                        ]
                                    )
                                    : 'Not Assigned'
                                ?>
                            </strong>

                        </div>

                    </div>


                    <div class="premium-profile-item">

                        <div class="premium-profile-icon">
                            <i class="bi bi-calendar-check"></i>
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
                                            $employeeProfile[
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
                    Secure profile information
                </strong>

                <p>
                    This page only displays information
                    associated with your own authenticated
                    Employee account.
                </p>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__
    . '/../includes/employee/footer.php';
