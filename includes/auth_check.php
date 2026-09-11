<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';


/*
|--------------------------------------------------------------------------
| Session Idle Timeout
|--------------------------------------------------------------------------
|
| A logged-in user is automatically signed out after
| 30 minutes without activity.
|
*/

const SESSION_IDLE_TIMEOUT = 1800;


/*
|--------------------------------------------------------------------------
| Secure Session Termination
|--------------------------------------------------------------------------
*/

$terminateSession =
    static function (): void {

        $_SESSION = [];


        if (
            session_status()
            === PHP_SESSION_ACTIVE
            &&
            ini_get(
                'session.use_cookies'
            )
        ) {

            $cookieParams =
                session_get_cookie_params();


            setcookie(
                session_name(),
                '',
                [
                    'expires' =>
                        time() - 42000,

                    'path' =>
                        $cookieParams[
                            'path'
                        ] ?: '/',

                    'domain' =>
                        $cookieParams[
                            'domain'
                        ] ?? '',

                    'secure' =>
                        true,

                    'httponly' =>
                        true,

                    'samesite' =>
                        'Lax'
                ]
            );
        }


        if (
            session_status()
            === PHP_SESSION_ACTIVE
        ) {

            session_destroy();
        }


        header(
            'Location: /login.php'
        );

        exit;
    };


/*
|--------------------------------------------------------------------------
| Prevent Protected Pages From Being Cached
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
| Require Authentication
|--------------------------------------------------------------------------
*/

$userIdRaw =
    $_SESSION['user_id']
    ?? null;


if (
    !is_int(
        $userIdRaw
    )
    &&
    !is_string(
        $userIdRaw
    )
) {

    $terminateSession();
}


$userId =
    filter_var(
        $userIdRaw,
        FILTER_VALIDATE_INT
    );


if (
    !$userId
    ||
    $userId < 1
) {

    $terminateSession();
}


/*
|--------------------------------------------------------------------------
| Check Session Inactivity
|--------------------------------------------------------------------------
*/

$currentTime =
    time();


$lastActivityRaw =
    $_SESSION['last_activity']
    ?? null;


if (
    $lastActivityRaw
    !== null
    &&
    !is_int(
        $lastActivityRaw
    )
    &&
    !is_string(
        $lastActivityRaw
    )
) {

    $terminateSession();
}


$lastActivity =
    $lastActivityRaw === null
        ? $currentTime
        : filter_var(
            $lastActivityRaw,
            FILTER_VALIDATE_INT
        );


if (
    $lastActivity === false
    ||
    $lastActivity < 0
) {

    $terminateSession();
}


if (
    ($currentTime - $lastActivity)
    >= SESSION_IDLE_TIMEOUT
) {

    $terminateSession();
}


/*
|--------------------------------------------------------------------------
| Load Current Account From Database
|--------------------------------------------------------------------------
|
| Protected requests must use the current database account state,
| not only the role/status stored when the session was created.
|
*/

require_once __DIR__
    . '/../config/database.php';


$accountStmt =
    $pdo->prepare(
        "SELECT
            u.user_id,
            u.status AS user_status,

            r.role_name,

            e.employee_id,
            e.status AS employee_status

         FROM users u

         INNER JOIN roles r
            ON u.role_id =
               r.role_id

         LEFT JOIN employees e
            ON e.user_id =
               u.user_id

         WHERE u.user_id =
            :user_id

         LIMIT 1"
    );


$accountStmt->execute([
    'user_id' =>
        (int)$userId
]);


$account =
    $accountStmt->fetch();


/*
|--------------------------------------------------------------------------
| Validate Current Account State
|--------------------------------------------------------------------------
*/

if (!$account) {

    $terminateSession();
}


if (
    $account['user_status']
    !== 'Active'
) {

    $terminateSession();
}


$currentRole =
    $account['role_name']
    ?? null;


if (
    !is_string(
        $currentRole
    )
    ||
    !in_array(
        $currentRole,
        [
            'Administrator',
            'Manager',
            'Employee'
        ],
        true
    )
) {

    $terminateSession();
}


/*
|--------------------------------------------------------------------------
| Employee / Manager Profile Must Also Be Active
|--------------------------------------------------------------------------
|
| Administrators do not require an employee profile.
|
*/

if (
    in_array(
        $currentRole,
        [
            'Employee',
            'Manager'
        ],
        true
    )
    &&
    (
        empty(
            $account[
                'employee_id'
            ]
        )
        ||
        $account[
            'employee_status'
        ] !== 'Active'
    )
) {

    $terminateSession();
}


/*
|--------------------------------------------------------------------------
| Reject Stale Role Sessions
|--------------------------------------------------------------------------
|
| If an Administrator changes a user's role while that user is logged in,
| the old session is invalidated immediately. The user must sign in again
| to receive the new permissions.
|
*/

$sessionRole =
    $_SESSION['role']
    ?? null;


if (
    !is_string(
        $sessionRole
    )
    ||
    $sessionRole !==
        $currentRole
) {

    $terminateSession();
}


/*
|--------------------------------------------------------------------------
| Refresh Activity Time
|--------------------------------------------------------------------------
|
| Only a valid, currently active account reaches this point.
|
*/

$_SESSION['last_activity'] =
    $currentTime;
