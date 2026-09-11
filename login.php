<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';


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
        $_SESSION['role']
    );
}


$error = '';


/*
|--------------------------------------------------------------------------
| Login Form Submission
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    $email =
        trim(
            $_POST['email']
            ?? ''
        );


    $password =
        $_POST['password']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (
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
            | Verify Account And Password
            |--------------------------------------------------------------------------
            */

            if (
                $user
                &&
                $user['status']
                    === 'Active'
                &&
                password_verify(
                    $password,
                    $user[
                        'password_hash'
                    ]
                )
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
                    $user[
                        'email'
                    ];


                $_SESSION['role'] =
                    $user[
                        'role_name'
                    ];


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
                    $user[
                        'role_name'
                    ]
                );


            } else {

                /*
                |--------------------------------------------------------------------------
                | Invalid Login
                |--------------------------------------------------------------------------
                */

                $error =
                    'Invalid email or password.';
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
            autocomplete="off"
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
                    autocomplete="username"
                    value="<?= escape(
                        $_POST[
                            'email'
                        ]
                        ?? ''
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