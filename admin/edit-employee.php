<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');

$pageTitle = 'Edit Employee';
$error = '';

$employeeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$employeeId) {

    setFlash(
        'danger',
        'Invalid employee selected.'
    );

    header(
        'Location: /admin/employees.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Employee
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        e.employee_id,
        e.user_id,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.phone,
        e.department_id,
        e.manager_id,
        e.job_title,
        e.date_joined,
        e.status AS employee_status,
        e.created_at,

        u.email,
        u.role_id,
        u.status AS user_status,

        r.role_name

     FROM employees e

     INNER JOIN users u
        ON e.user_id = u.user_id

     INNER JOIN roles r
        ON u.role_id = r.role_id

     WHERE e.employee_id = :employee_id

     LIMIT 1"
);

$stmt->execute([
    'employee_id' => $employeeId
]);

$employee = $stmt->fetch();

if (!$employee) {

    setFlash(
        'danger',
        'Employee not found.'
    );

    header(
        'Location: /admin/employees.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Departments
|--------------------------------------------------------------------------
*/

$departmentStmt = $pdo->query(
    "SELECT
        department_id,
        department_name
     FROM departments
     WHERE status = 'Active'
     ORDER BY department_name"
);

$departments = $departmentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Roles
|--------------------------------------------------------------------------
*/

$roleStmt = $pdo->query(
    "SELECT
        role_id,
        role_name
     FROM roles
     WHERE role_name IN (
        'Manager',
        'Employee'
     )
     ORDER BY role_name"
);

$roles = $roleStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Available Managers
|--------------------------------------------------------------------------
*/

$managerStmt = $pdo->prepare(
    "SELECT
        e.employee_id,
        e.employee_code,
        e.first_name,
        e.last_name

     FROM employees e

     INNER JOIN users u
        ON e.user_id = u.user_id

     INNER JOIN roles r
        ON u.role_id = r.role_id

     WHERE
        e.status = 'Active'
        AND u.status = 'Active'
        AND r.role_name = 'Manager'
        AND e.employee_id != :employee_id

     ORDER BY
        e.first_name,
        e.last_name"
);

$managerStmt->execute([
    'employee_id' => $employeeId
]);

$managers = $managerStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Update Employee
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !verifyCsrfToken(
            $_POST['csrf_token'] ?? null
        )
    ) {

        http_response_code(403);

        exit('Invalid security token.');
    }


    $employeeCode = strtoupper(
        trim(
            $_POST['employee_code']
            ?? ''
        )
    );

    $firstName = trim(
        $_POST['first_name']
        ?? ''
    );

    $lastName = trim(
        $_POST['last_name']
        ?? ''
    );

    $email = strtolower(
        trim(
            $_POST['email']
            ?? ''
        )
    );

    $phone = trim(
        $_POST['phone']
        ?? ''
    );

    $jobTitle = trim(
        $_POST['job_title']
        ?? ''
    );

    $dateJoined =
        $_POST['date_joined']
        ?? '';

    $departmentId =
        filter_var(
            $_POST['department_id']
            ?? null,
            FILTER_VALIDATE_INT
        );

    $roleId =
        filter_var(
            $_POST['role_id']
            ?? null,
            FILTER_VALIDATE_INT
        );

    $managerId =
        filter_var(
            $_POST['manager_id']
            ?? null,
            FILTER_VALIDATE_INT
        );

    $newPassword =
        $_POST['new_password']
        ?? '';

    $confirmPassword =
        $_POST['confirm_password']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $employeeCode === '' ||
        $firstName === '' ||
        $lastName === '' ||
        $email === '' ||
        $jobTitle === '' ||
        $dateJoined === '' ||
        !$departmentId ||
        !$roleId
    ) {

        $error =
            'Please complete all required fields.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } elseif (
        strtotime($dateJoined) > time()
    ) {

        $error =
            'Date joined cannot be in the future.';

    } elseif (
        $newPassword !== ''
        &&
        strlen($newPassword) < 8
    ) {

        $error =
            'New password must contain at least 8 characters.';

    } elseif (
        $newPassword !==
        $confirmPassword
    ) {

        $error =
            'New passwords do not match.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Validate Role
            |--------------------------------------------------------------------------
            */

            $selectedRoleStmt =
                $pdo->prepare(
                    "SELECT
                        role_id,
                        role_name
                     FROM roles
                     WHERE role_id = :role_id
                       AND role_name IN (
                           'Manager',
                           'Employee'
                       )
                     LIMIT 1"
                );

            $selectedRoleStmt->execute([
                'role_id' => $roleId
            ]);

            $selectedRole =
                $selectedRoleStmt->fetch();

            if (!$selectedRole) {

                throw new RuntimeException(
                    'Invalid role selected.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Employees Require Manager
            |--------------------------------------------------------------------------
            */

            if (
                $selectedRole['role_name']
                === 'Employee'
                &&
                !$managerId
            ) {

                throw new RuntimeException(
                    'Please assign a Manager to this Employee.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Prevent Manager → Employee Change
            | if Manager still has active employees
            |--------------------------------------------------------------------------
            */

            if (
                $employee['role_name']
                === 'Manager'
                &&
                $selectedRole['role_name']
                === 'Employee'
            ) {

                $teamStmt =
                    $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM employees
                         WHERE manager_id = :manager_id
                           AND status = 'Active'"
                    );

                $teamStmt->execute([
                    'manager_id' =>
                        $employeeId
                ]);

                $activeTeam =
                    (int)$teamStmt
                        ->fetchColumn();

                if ($activeTeam > 0) {

                    throw new RuntimeException(
                        'This Manager cannot be changed to Employee because active employees are still assigned to them.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Validate Department
            |--------------------------------------------------------------------------
            */

            $departmentCheck =
                $pdo->prepare(
                    "SELECT department_id
                     FROM departments
                     WHERE department_id =
                        :department_id
                     AND status = 'Active'
                     LIMIT 1"
                );

            $departmentCheck->execute([
                'department_id' =>
                    $departmentId
            ]);

            if (
                !$departmentCheck->fetch()
            ) {

                throw new RuntimeException(
                    'Invalid department selected.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Validate Manager
            |--------------------------------------------------------------------------
            */

            if (
                $selectedRole['role_name']
                === 'Employee'
            ) {

                if (
                    $managerId ===
                    $employeeId
                ) {

                    throw new RuntimeException(
                        'An employee cannot be their own Manager.'
                    );
                }

                $managerCheck =
                    $pdo->prepare(
                        "SELECT e.employee_id
                         FROM employees e

                         INNER JOIN users u
                            ON e.user_id = u.user_id

                         INNER JOIN roles r
                            ON u.role_id = r.role_id

                         WHERE
                            e.employee_id = :manager_id
                            AND e.status = 'Active'
                            AND u.status = 'Active'
                            AND r.role_name = 'Manager'

                         LIMIT 1"
                    );

                $managerCheck->execute([
                    'manager_id' =>
                        $managerId
                ]);

                if (
                    !$managerCheck->fetch()
                ) {

                    throw new RuntimeException(
                        'The selected Manager is invalid.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Email
            |--------------------------------------------------------------------------
            */

            $emailCheck =
                $pdo->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = :email
                       AND user_id != :user_id
                     LIMIT 1"
                );

            $emailCheck->execute([
                'email' =>
                    $email,

                'user_id' =>
                    $employee['user_id']
            ]);

            if (
                $emailCheck->fetch()
            ) {

                throw new RuntimeException(
                    'Another account already uses this email address.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Employee Code
            |--------------------------------------------------------------------------
            */

            $codeCheck =
                $pdo->prepare(
                    "SELECT employee_id
                     FROM employees
                     WHERE employee_code =
                        :employee_code
                       AND employee_id !=
                        :employee_id
                     LIMIT 1"
                );

            $codeCheck->execute([
                'employee_code' =>
                    $employeeCode,

                'employee_id' =>
                    $employeeId
            ]);

            if (
                $codeCheck->fetch()
            ) {

                throw new RuntimeException(
                    'Another employee already uses this employee code.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Begin Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Update User Account
            |--------------------------------------------------------------------------
            */

            if ($newPassword !== '') {

                $passwordHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                $userUpdate =
                    $pdo->prepare(
                        "UPDATE users
                         SET
                            email = :email,
                            role_id = :role_id,
                            password_hash =
                                :password_hash
                         WHERE user_id = :user_id"
                    );

                $userUpdate->execute([
                    'email' =>
                        $email,

                    'role_id' =>
                        $roleId,

                    'password_hash' =>
                        $passwordHash,

                    'user_id' =>
                        $employee['user_id']
                ]);

            } else {

                $userUpdate =
                    $pdo->prepare(
                        "UPDATE users
                         SET
                            email = :email,
                            role_id = :role_id
                         WHERE user_id = :user_id"
                    );

                $userUpdate->execute([
                    'email' =>
                        $email,

                    'role_id' =>
                        $roleId,

                    'user_id' =>
                        $employee['user_id']
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Update Employee
            |--------------------------------------------------------------------------
            */

            $employeeUpdate =
                $pdo->prepare(
                    "UPDATE employees
                     SET
                        employee_code =
                            :employee_code,

                        first_name =
                            :first_name,

                        last_name =
                            :last_name,

                        phone =
                            :phone,

                        department_id =
                            :department_id,

                        manager_id =
                            :manager_id,

                        job_title =
                            :job_title,

                        date_joined =
                            :date_joined

                     WHERE employee_id =
                        :employee_id"
                );


            $employeeUpdate->execute([
                'employee_code' =>
                    $employeeCode,

                'first_name' =>
                    $firstName,

                'last_name' =>
                    $lastName,

                'phone' =>
                    $phone !== ''
                        ? $phone
                        : null,

                'department_id' =>
                    $departmentId,

                'manager_id' =>
                    $selectedRole['role_name']
                    === 'Employee'
                        ? $managerId
                        : null,

                'job_title' =>
                    $jobTitle,

                'date_joined' =>
                    $dateJoined,

                'employee_id' =>
                    $employeeId
            ]);


            $pdo->commit();


            setFlash(
                'success',
                'Employee information updated successfully.'
            );


            header(
                'Location: /admin/employees.php'
            );

            exit;


        } catch (Throwable $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            error_log(
                'Edit employee error: '
                . $e->getMessage()
            );


            $error =
                $e instanceof
                RuntimeException
                    ? $e->getMessage()
                    : 'Unable to update the employee.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Preserve Submitted Data
    |--------------------------------------------------------------------------
    */

    $employee['employee_code'] =
        $employeeCode;

    $employee['first_name'] =
        $firstName;

    $employee['last_name'] =
        $lastName;

    $employee['email'] =
        $email;

    $employee['phone'] =
        $phone;

    $employee['department_id'] =
        $departmentId;

    $employee['role_id'] =
        $roleId;

    $employee['manager_id'] =
        $managerId;

    $employee['job_title'] =
        $jobTitle;

    $employee['date_joined'] =
        $dateJoined;

    $employee['role_name'] =
        $selectedRole['role_name']
        ?? $employee['role_name'];
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

        <a href="/admin/employees.php">
            Employees
        </a>

        <i class="bi bi-chevron-right"></i>

        <span>
            Edit
        </span>

    </div>


    <h2>
        Edit Employee
    </h2>

    <p>
        Update employee information,
        account access and organizational
        assignment.
    </p>

</div>


<?php if ($error !== ''): ?>

    <div
        class="alert alert-danger
               d-flex gap-2
               align-items-center"
    >

        <i
            class="bi
                   bi-exclamation-triangle-fill"
        ></i>

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


    <div class="row g-4">

        <div class="col-xl-8">

            <!-- PERSONAL INFORMATION -->

            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Personal Information
                        </h5>

                        <small class="text-muted">
                            Identification and
                            contact information
                        </small>

                    </div>

                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-person-vcard"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="row g-3">

                        <div class="col-md-4">

                            <label
                                class="professional-form-label"
                            >
                                Employee Code
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <input
                                type="text"
                                name="employee_code"
                                maxlength="30"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee[
                                        'employee_code'
                                    ]
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-4">

                            <label
                                class="professional-form-label"
                            >
                                First Name
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                maxlength="100"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee[
                                        'first_name'
                                    ]
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-4">

                            <label
                                class="professional-form-label"
                            >
                                Last Name
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                maxlength="100"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee[
                                        'last_name'
                                    ]
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                class="professional-form-label"
                            >
                                Email Address
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                maxlength="150"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee['email']
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                class="professional-form-label"
                            >
                                Phone Number
                            </label>

                            <input
                                type="text"
                                name="phone"
                                maxlength="20"
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee[
                                        'phone'
                                    ] ?? ''
                                ) ?>"
                            >

                        </div>

                    </div>

                </div>

            </div>


            <!-- EMPLOYMENT INFORMATION -->

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Employment Information
                        </h5>

                        <small class="text-muted">
                            Role, department and
                            reporting relationship
                        </small>

                    </div>

                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-building"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label
                                class="professional-form-label"
                            >
                                Department
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <select
                                name="department_id"
                                class="form-select
                                       professional-input"
                                required
                            >

                                <?php foreach (
                                    $departments
                                    as $department
                                ): ?>

                                    <option
                                        value="<?= (int)$department['department_id'] ?>"
                                        <?= (
                                            (int)$employee[
                                                'department_id'
                                            ]
                                            ===
                                            (int)$department[
                                                'department_id'
                                            ]
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= escape(
                                            $department[
                                                'department_name'
                                            ]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label
                                class="professional-form-label"
                            >
                                User Role
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <select
                                name="role_id"
                                id="roleSelect"
                                class="form-select
                                       professional-input"
                                required
                            >

                                <?php foreach (
                                    $roles
                                    as $role
                                ): ?>

                                    <option
                                        value="<?= (int)$role['role_id'] ?>"
                                        data-role="<?= escape(
                                            $role['role_name']
                                        ) ?>"
                                        <?= (
                                            (int)$employee[
                                                'role_id'
                                            ]
                                            ===
                                            (int)$role[
                                                'role_id'
                                            ]
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= escape(
                                            $role[
                                                'role_name'
                                            ]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label
                                class="professional-form-label"
                            >
                                Job Title
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <input
                                type="text"
                                name="job_title"
                                maxlength="100"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee[
                                        'job_title'
                                    ]
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label
                                class="professional-form-label"
                            >
                                Date Joined
                                <span class="required-mark">
                                    *
                                </span>
                            </label>

                            <input
                                type="date"
                                name="date_joined"
                                max="<?= date('Y-m-d') ?>"
                                required
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $employee[
                                        'date_joined'
                                    ]
                                ) ?>"
                            >

                        </div>


                        <div
                            class="col-12"
                            id="managerField"
                        >

                            <label
                                class="professional-form-label"
                            >
                                Assigned Manager
                            </label>

                            <select
                                name="manager_id"
                                id="managerSelect"
                                class="form-select
                                       professional-input"
                            >

                                <option value="">
                                    Select Manager
                                </option>

                                <?php foreach (
                                    $managers
                                    as $manager
                                ): ?>

                                    <option
                                        value="<?= (int)$manager['employee_id'] ?>"
                                        <?= (
                                            (int)(
                                                $employee[
                                                    'manager_id'
                                                ] ?? 0
                                            )
                                            ===
                                            (int)$manager[
                                                'employee_id'
                                            ]
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= escape(
                                            $manager[
                                                'first_name'
                                            ]
                                            . ' '
                                            . $manager[
                                                'last_name'
                                            ]
                                            . ' ('
                                            . $manager[
                                                'employee_code'
                                            ]
                                            . ')'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <div class="form-help">
                                Required when the
                                account role is Employee.
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ACCOUNT PANEL -->

        <div class="col-xl-4">

            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Account Information
                        </h5>

                        <small class="text-muted">
                            ELMS access details
                        </small>

                    </div>

                    <div class="header-icon-box">

                        <i
                            class="bi
                                   bi-shield-check"
                        ></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="detail-list">

                        <div class="detail-item">

                            <div class="detail-icon">
                                <i class="bi bi-person-badge"></i>
                            </div>

                            <div>

                                <span>
                                    Employee ID
                                </span>

                                <strong>
                                    #<?= (int)$employeeId ?>
                                </strong>

                            </div>

                        </div>


                        <div class="detail-item">

                            <div class="detail-icon">
                                <i class="bi bi-toggle-on"></i>
                            </div>

                            <div>

                                <span>
                                    Account Status
                                </span>

                                <strong>
                                    <?= escape(
                                        $employee[
                                            'employee_status'
                                        ]
                                    ) ?>
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
                                                $employee[
                                                    'created_at'
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


            <!-- PASSWORD RESET -->

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Reset Password
                        </h5>

                        <small class="text-muted">
                            Optional
                        </small>

                    </div>

                    <div class="header-icon-box">

                        <i class="bi bi-key-fill"></i>

                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="mb-3">

                        <label
                            class="professional-form-label"
                        >
                            New Password
                        </label>

                        <input
                            type="password"
                            name="new_password"
                            minlength="8"
                            autocomplete="new-password"
                            class="form-control
                                   professional-input"
                        >

                    </div>


                    <div class="mb-3">

                        <label
                            class="professional-form-label"
                        >
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            class="form-control
                                   professional-input"
                        >

                    </div>


                    <div class="admin-info-box">

                        <div class="admin-info-icon">
                            <i class="bi bi-lock"></i>
                        </div>

                        <div>

                            <strong>
                                Password Security
                            </strong>

                            <p>
                                Leave both fields empty
                                to keep the current password.
                            </p>

                        </div>

                    </div>


                    <div class="form-actions">

                        <a
                            href="/admin/employees.php"
                            class="btn-professional-secondary"
                        >
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

                </div>

            </div>

        </div>

    </div>

</form>


<?php

require_once __DIR__ .
    '/../includes/admin/footer.php';