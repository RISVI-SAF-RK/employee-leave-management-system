<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');

$pageTitle = 'Add Employee';

$error = '';


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

$departments =
    $departmentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Managers
|--------------------------------------------------------------------------
*/

$managerStmt = $pdo->query(
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

     ORDER BY
        e.first_name,
        e.last_name"
);

$managers =
    $managerStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Allowed Roles
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

$roles =
    $roleStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Safe Form Values
|--------------------------------------------------------------------------
|
| Keep scalar values for validation and form repopulation. This prevents
| malformed array inputs from causing TypeError exceptions with strict_types.
|
*/

$employeeCode = '';
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$jobTitle = '';
$dateJoined = '';

$departmentInput = '';
$roleInput = '';
$managerInput = '';


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

    $csrfToken =
        $_POST['csrf_token']
        ?? null;


    if (
        !is_string(
            $csrfToken
        )
        ||
        !verifyCsrfToken(
            $csrfToken
        )
    ) {

        http_response_code(403);

        exit(
            'Invalid security token.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Scalar Inputs
    |--------------------------------------------------------------------------
    */

    $employeeCodeInput =
        $_POST['employee_code']
        ?? '';


    $employeeCode =
        is_string(
            $employeeCodeInput
        )
            ? strtoupper(
                trim(
                    $employeeCodeInput
                )
            )
            : '';


    $firstNameInput =
        $_POST['first_name']
        ?? '';


    $firstName =
        is_string(
            $firstNameInput
        )
            ? trim(
                $firstNameInput
            )
            : '';


    $lastNameInput =
        $_POST['last_name']
        ?? '';


    $lastName =
        is_string(
            $lastNameInput
        )
            ? trim(
                $lastNameInput
            )
            : '';


    $emailInput =
        $_POST['email']
        ?? '';


    $email =
        is_string(
            $emailInput
        )
            ? strtolower(
                trim(
                    $emailInput
                )
            )
            : '';


    $phoneInput =
        $_POST['phone']
        ?? '';


    $phone =
        is_string(
            $phoneInput
        )
            ? trim(
                $phoneInput
            )
            : '';


    $jobTitleInput =
        $_POST['job_title']
        ?? '';


    $jobTitle =
        is_string(
            $jobTitleInput
        )
            ? trim(
                $jobTitleInput
            )
            : '';


    $dateJoinedInput =
        $_POST['date_joined']
        ?? '';


    $dateJoined =
        is_string(
            $dateJoinedInput
        )
            ? trim(
                $dateJoinedInput
            )
            : '';


    $departmentRaw =
        $_POST['department_id']
        ?? '';


    $departmentInput =
        is_string(
            $departmentRaw
        )
            ? trim(
                $departmentRaw
            )
            : '';


    $departmentId =
        filter_var(
            $departmentInput,
            FILTER_VALIDATE_INT
        );


    $roleRaw =
        $_POST['role_id']
        ?? '';


    $roleInput =
        is_string(
            $roleRaw
        )
            ? trim(
                $roleRaw
            )
            : '';


    $roleId =
        filter_var(
            $roleInput,
            FILTER_VALIDATE_INT
        );


    $managerRaw =
        $_POST['manager_id']
        ?? '';


    $managerInput =
        is_string(
            $managerRaw
        )
            ? trim(
                $managerRaw
            )
            : '';


    $managerId =
        $managerInput !== ''
            ? filter_var(
                $managerInput,
                FILTER_VALIDATE_INT
            )
            : null;


    $passwordInput =
        $_POST['password']
        ?? '';


    $password =
        is_string(
            $passwordInput
        )
            ? $passwordInput
            : '';


    $confirmPasswordInput =
        $_POST['confirm_password']
        ?? '';


    $confirmPassword =
        is_string(
            $confirmPasswordInput
        )
            ? $confirmPasswordInput
            : '';


    /*
    |--------------------------------------------------------------------------
    | Exact Date Validation Helper
    |--------------------------------------------------------------------------
    */

    $dateJoinedObject =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dateJoined
        );


    $dateJoinedErrors =
        DateTimeImmutable::getLastErrors();


    $dateJoinedIsValid =
        $dateJoinedObject
        instanceof DateTimeImmutable
        &&
        $dateJoinedObject->format('Y-m-d')
            === $dateJoined
        &&
        (
            $dateJoinedErrors === false
            ||
            (
                ($dateJoinedErrors['warning_count'] ?? 0) === 0
                &&
                ($dateJoinedErrors['error_count'] ?? 0) === 0
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (
        $employeeCode === ''
        ||
        $firstName === ''
        ||
        $lastName === ''
        ||
        $email === ''
        ||
        $jobTitle === ''
        ||
        $dateJoined === ''
        ||
        !$departmentId
        ||
        !$roleId
    ) {

        $error =
            'Please complete all required fields.';

    } elseif (
        strlen(
            $employeeCode
        ) > 30
        ||
        strlen(
            $firstName
        ) > 100
        ||
        strlen(
            $lastName
        ) > 100
        ||
        strlen(
            $email
        ) > 150
        ||
        strlen(
            $phone
        ) > 20
        ||
        strlen(
            $jobTitle
        ) > 100
    ) {

        $error =
            'One or more fields exceed the allowed length.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } elseif (
        strlen(
            $password
        ) < 8
    ) {

        $error =
            'Password must contain at least 8 characters.';

    } elseif (
        strlen(
            $password
        ) > 255
    ) {

        $error =
            'Password cannot exceed 255 characters.';

    } elseif (
        $password !==
        $confirmPassword
    ) {

        $error =
            'Passwords do not match.';

    } elseif (
        !$dateJoinedIsValid
    ) {

        $error =
            'Please enter a valid date joined.';

    } elseif (
        $dateJoinedObject
        >
        new DateTimeImmutable(
            'today'
        )
    ) {

        $error =
            'Date joined cannot be in the future.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Validate Selected Role
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
                            'Employee',
                            'Manager'
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
                    'Invalid user role selected.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Employee must have an assigned Manager
            |--------------------------------------------------------------------------
            */

            if (
                $selectedRole['role_name']
                === 'Employee'
                &&
                !$managerId
            ) {

                throw new RuntimeException(
                    'Please assign a Manager to the Employee.'
                );
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

            if ($managerId) {

                $managerCheck =
                    $pdo->prepare(
                        "SELECT e.employee_id
                         FROM employees e

                         INNER JOIN users u
                            ON e.user_id =
                               u.user_id

                         INNER JOIN roles r
                            ON u.role_id =
                               r.role_id

                         WHERE
                            e.employee_id =
                                :manager_id
                            AND e.status =
                                'Active'
                            AND u.status =
                                'Active'
                            AND r.role_name =
                                'Manager'

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
                        'Invalid Manager selected.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Email
            |--------------------------------------------------------------------------
            */

            $emailCheck =
                $pdo->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = :email
                     LIMIT 1"
                );

            $emailCheck->execute([
                'email' => $email
            ]);

            if ($emailCheck->fetch()) {

                throw new RuntimeException(
                    'An account already exists with this email address.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Employee Code
            |--------------------------------------------------------------------------
            */

            $codeCheck =
                $pdo->prepare(
                    "SELECT employee_id
                     FROM employees
                     WHERE employee_code =
                        :employee_code
                     LIMIT 1"
                );

            $codeCheck->execute([
                'employee_code' =>
                    $employeeCode
            ]);

            if ($codeCheck->fetch()) {

                throw new RuntimeException(
                    'This employee code is already in use.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            /*
            |--------------------------------------------------------------------------
            | Create User Account
            |--------------------------------------------------------------------------
            */

            $userStmt =
                $pdo->prepare(
                    "INSERT INTO users
                    (
                        email,
                        password_hash,
                        role_id,
                        status
                    )
                    VALUES
                    (
                        :email,
                        :password_hash,
                        :role_id,
                        'Active'
                    )"
                );

            $userStmt->execute([
                'email' =>
                    $email,

                'password_hash' =>
                    $passwordHash,

                'role_id' =>
                    $roleId
            ]);


            $userId =
                (int)$pdo
                    ->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Create Employee Record
            |--------------------------------------------------------------------------
            */

            $employeeStmt =
                $pdo->prepare(
                    "INSERT INTO employees
                    (
                        user_id,
                        employee_code,
                        first_name,
                        last_name,
                        phone,
                        department_id,
                        manager_id,
                        job_title,
                        date_joined,
                        status
                    )
                    VALUES
                    (
                        :user_id,
                        :employee_code,
                        :first_name,
                        :last_name,
                        :phone,
                        :department_id,
                        :manager_id,
                        :job_title,
                        :date_joined,
                        'Active'
                    )"
                );


            $employeeStmt->execute([
                'user_id' =>
                    $userId,

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
                    $dateJoined
            ]);


            /*
            |--------------------------------------------------------------------------
            | Newly Created Employee ID
            |--------------------------------------------------------------------------
            */

            $employeeId =
                (int)$pdo
                    ->lastInsertId();


            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Audit Employee Account Creation
            |--------------------------------------------------------------------------
            */

            logAudit(
                $pdo,
                'EMPLOYEE_CREATED',
                'employee',
                $employeeId,
                'Created '
                . $selectedRole['role_name']
                . ' account '
                . $employeeCode
                . ' for '
                . $firstName
                . ' '
                . $lastName
                . '.'
            );


            setFlash(
                'success',
                $selectedRole['role_name']
                . ' account created successfully.'
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
                'Add employee error: '
                . $e->getMessage()
            );


            $error =
                $e instanceof
                RuntimeException
                    ? $e->getMessage()
                    : 'Unable to create the employee account.';
        }
    }
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
            Add Employee
        </span>

    </div>

    <h2>
        Add Employee
    </h2>

    <p>
        Create an employee profile and
        secure ELMS login account.
    </p>

</div>


<?php if (!$departments): ?>

    <div class="alert alert-warning">

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

        You need at least one active department
        before creating employees.

    </div>

<?php endif; ?>


<?php if ($error !== ''): ?>

    <div class="alert alert-danger">

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

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

            <div class="admin-card mb-4">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Personal Information
                        </h5>

                        <small class="text-muted">
                            Employee identification
                            and contact information
                        </small>

                    </div>

                    <div class="header-icon-box">
                        <i class="bi bi-person-vcard"></i>
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
                                placeholder="EMP001"
                                value="<?= escape(
                                    $employeeCode
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
                                    $firstName
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
                                    $lastName
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
                                autocomplete="off"
                                class="form-control
                                       professional-input"
                                value="<?= escape(
                                    $email
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
                                    $phone
                                ) ?>"
                            >

                        </div>

                    </div>

                </div>

            </div>


            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Employment Information
                        </h5>

                        <small class="text-muted">
                            Department, role and
                            reporting information
                        </small>

                    </div>

                    <div class="header-icon-box">
                        <i class="bi bi-building"></i>
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

                                <option value="">
                                    Select Department
                                </option>

                                <?php foreach (
                                    $departments
                                    as $department
                                ): ?>

                                    <option
                                        value="<?= (int)$department['department_id'] ?>"
                                        <?= (
                                            $departmentInput
                                            ===
                                            (string)$department[
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

                                <option value="">
                                    Select Role
                                </option>

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
                                            $roleInput
                                            ===
                                            (string)$role[
                                                'role_id'
                                            ]
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= escape(
                                            $role['role_name']
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
                                placeholder="Software Engineer"
                                value="<?= escape(
                                    $jobTitle
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
                                    $dateJoined
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
                                            $managerInput
                                            ===
                                            (string)$manager[
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
                                Employees must be assigned
                                to an active Manager.
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-xl-4">

            <div class="admin-card">

                <div class="admin-card-header">

                    <div>

                        <h5>
                            Account Security
                        </h5>

                        <small class="text-muted">
                            Initial ELMS login credentials
                        </small>

                    </div>

                    <div class="header-icon-box">
                        <i class="bi bi-shield-lock"></i>
                    </div>

                </div>


                <div class="admin-card-body">

                    <div class="mb-3">

                        <label
                            class="professional-form-label"
                        >
                            Temporary Password
                            <span class="required-mark">
                                *
                            </span>
                        </label>

                        <input
                            type="password"
                            name="password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            class="form-control
                                   professional-input"
                        >

                        <div class="form-help">
                            Minimum 8 characters.
                            Passwords are securely hashed
                            before storage.
                        </div>

                    </div>


                    <div class="mb-4">

                        <label
                            class="professional-form-label"
                        >
                            Confirm Password
                            <span class="required-mark">
                                *
                            </span>
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            class="form-control
                                   professional-input"
                        >

                    </div>


                    <div class="admin-info-box">

                        <div class="admin-info-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>

                        <div>

                            <strong>
                                Secure Account Creation
                            </strong>

                            <p>
                                Login credentials and employee
                                records are created together
                                using a database transaction.
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
                            <?= !$departments
                                ? 'disabled'
                                : ''
                            ?>
                        >
                            <i class="bi bi-person-plus-fill"></i>

                            Create Account
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