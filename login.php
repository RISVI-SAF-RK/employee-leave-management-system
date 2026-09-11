<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| Login Security Settings
|--------------------------------------------------------------------------
|
| This is a lightweight session-based login throttle.
| Five failed attempts will temporarily block further attempts for 60 seconds.
|
*/

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 60;


/*
|--------------------------------------------------------------------------
| Prevent Login Page Caching
|--------------------------------------------------------------------------
*/

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

header(
    'Pragma: no-cache'
);


/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION['user_id'],
        $_SESSION['role']
    )
) {

    redirectByRole(
        (string)$_SESSION['role']
    );
}


/*
|--------------------------------------------------------------------------
| Login CSRF Token
|--------------------------------------------------------------------------
*/

if (
    empty(
        $_SESSION['login_csrf_token']
    )
    ||
    !is_string(
        $_SESSION['login_csrf_token']
    )
) {

    $_SESSION['login_csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}


$loginCsrfToken =
    $_SESSION['login_csrf_token'];


/*
|--------------------------------------------------------------------------
| Login Attempt State
|--------------------------------------------------------------------------
*/

$failedAttempts =
    (int)(
        $_SESSION['login_failed_attempts']
        ?? 0
    );


$lockedUntil =
    (int)(
        $_SESSION['login_locked_until']
        ?? 0
    );


if (
    $lockedUntil > 0
    &&
    $lockedUntil <= time()
) {

    unset(
        $_SESSION['login_failed_attempts'],
        $_SESSION['login_locked_until']
    );

    $failedAttempts = 0;
    $lockedUntil = 0;
}


$error = '';
$email = '';


/*
|--------------------------------------------------------------------------
| Login Form Submission
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    $emailInput =
        $_POST['email']
        ?? '';


    $passwordInput =
        $_POST['password']
        ?? '';


    $csrfInput =
        $_POST['csrf_token']
        ?? '';


    $email =
        is_string(
            $emailInput
        )
            ? trim(
                $emailInput
            )
            : '';


    $password =
        is_string(
            $passwordInput
        )
            ? $passwordInput
            : '';


    $submittedCsrfToken =
        is_string(
            $csrfInput
        )
            ? $csrfInput
            : '';


    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    if (
        $submittedCsrfToken === ''
        ||
        !hash_equals(
            $loginCsrfToken,
            $submittedCsrfToken
        )
    ) {

        $error =
            'Your sign-in session expired. Please refresh the page and try again.';


    /*
    |--------------------------------------------------------------------------
    | Temporary Login Lock
    |--------------------------------------------------------------------------
    */

    } elseif (
        $lockedUntil > time()
    ) {

        $remainingSeconds =
            max(
                1,
                $lockedUntil
                - time()
            );


        $error =
            'Too many failed sign-in attempts. Please wait '
            . $remainingSeconds
            . ' second(s) and try again.';


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    } elseif (
        $email === ''
        ||
        $password === ''
    ) {

        $error =
            'Please enter your email and password.';


    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';


    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Find User
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare(
                    "SELECT
                        u.user_id,
                        u.email,
                        u.password_hash,
                        u.status,
                        r.role_name

                     FROM users u

                     INNER JOIN roles r
                        ON u.role_id =
                           r.role_id

                     WHERE u.email =
                        :email

                     LIMIT 1"
                );


            $stmt->execute([
                'email' =>
                    $email
            ]);


            $user =
                $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | Verify Password
            |--------------------------------------------------------------------------
            |
            | Inactive accounts receive the same public error as incorrect
            | credentials so the login page does not reveal account status.
            |
            */

            $passwordMatches =
                $user
                &&
                password_verify(
                    $password,
                    (string)$user[
                        'password_hash'
                    ]
                );


            if (
                $user
                &&
                $user['status']
                    === 'Active'
                &&
                $passwordMatches
            ) {

                /*
                |--------------------------------------------------------------------------
                | Secure Session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(
                    true
                );


                $_SESSION['user_id'] =
                    (int)$user[
                        'user_id'
                    ];


                $_SESSION['email'] =
                    (string)$user[
                        'email'
                    ];


                $_SESSION['role'] =
                    (string)$user[
                        'role_name'
                    ];


                $_SESSION['authenticated_at'] =
                    time();


                /*
                |--------------------------------------------------------------------------
                | Clear Login Throttle And Login CSRF Data
                |--------------------------------------------------------------------------
                */

                unset(
                    $_SESSION['login_failed_attempts'],
                    $_SESSION['login_locked_until'],
                    $_SESSION['login_csrf_token']
                );


                /*
                |--------------------------------------------------------------------------
                | Update Last Login
                |--------------------------------------------------------------------------
                */

                $updateLogin =
                    $pdo->prepare(
                        "UPDATE users

                         SET last_login =
                             NOW()

                         WHERE user_id =
                             :user_id"
                    );


                $updateLogin->execute([
                    'user_id' =>
                        (int)$user[
                            'user_id'
                        ]
                ]);


                /*
                |--------------------------------------------------------------------------
                | Audit Successful Login
                |--------------------------------------------------------------------------
                */

                logAudit(
                    $pdo,
                    'LOGIN_SUCCESS',
                    'user',
                    (int)$user[
                        'user_id'
                    ],
                    'User logged into ELMS successfully.'
                );


                /*
                |--------------------------------------------------------------------------
                | Redirect To Correct Dashboard
                |--------------------------------------------------------------------------
                */

                redirectByRole(
                    (string)$user[
                        'role_name'
                    ]
                );


            } else {

                /*
                |--------------------------------------------------------------------------
                | Failed Login Attempt
                |--------------------------------------------------------------------------
                */

                $failedAttempts++;


                $_SESSION[
                    'login_failed_attempts'
                ] =
                    $failedAttempts;


                if (
                    $failedAttempts
                    >= LOGIN_MAX_ATTEMPTS
                ) {

                    $lockedUntil =
                        time()
                        + LOGIN_LOCK_SECONDS;


                    $_SESSION[
                        'login_locked_until'
                    ] =
                        $lockedUntil;


                    $error =
                        'Too many failed sign-in attempts. Please wait '
                        . LOGIN_LOCK_SECONDS
                        . ' seconds and try again.';


                } else {

                    $error =
                        'Invalid email or password.';
                }


                /*
                |--------------------------------------------------------------------------
                | Audit Failed Login
                |--------------------------------------------------------------------------
                |
                | Do not record the password or other secrets.
                |
                */

                logAudit(
                    $pdo,
                    'LOGIN_FAILED',
                    'user',
                    $user
                        ? (int)$user[
                            'user_id'
                        ]
                        : null,
                    'Failed ELMS sign-in attempt.'
                );
            }


        } catch (
            Throwable $e
        ) {

            error_log(
                'Login error: '
                . $e->getMessage()
            );


            $error =
                'Unable to sign in at the moment.';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login | ELMS
    </title>


    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>


<body class="auth-page">


<div class="login-container">

    <div class="login-card">

        <h1>
            Employee Leave Management System
        </h1>


        <p class="login-subtitle">
            Sign in to continue
        </p>


        <?php if (
            $error !== ''
        ): ?>

            <div class="error-message">

                <?= escape(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape(
                    $loginCsrfToken
                ) ?>"
            >


            <div class="form-group">

                <label for="email">
                    Email Address
                </label>


                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    maxlength="255"
                    autocomplete="username"
                    value="<?= escape(
                        $email
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >

            </div>


            <button
                type="submit"
                class="login-button"
            >

                Sign In

            </button>

        </form>

    </div>

</div>


</body>

</html>
