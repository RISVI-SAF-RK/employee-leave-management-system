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
| Require Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_SESSION['user_id']
    )
) {

    header(
        'Location: /login.php'
    );

    exit;
}


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
| Check Session Inactivity
|--------------------------------------------------------------------------
*/

$currentTime =
    time();


$lastActivity =
    isset(
        $_SESSION['last_activity']
    )
        ? (int)$_SESSION[
            'last_activity'
        ]
        : $currentTime;


if (
    ($currentTime - $lastActivity)
    >= SESSION_IDLE_TIMEOUT
) {

    /*
    |--------------------------------------------------------------------------
    | Clear Session Data
    |--------------------------------------------------------------------------
    */

    $_SESSION = [];


    /*
    |--------------------------------------------------------------------------
    | Delete Session Cookie
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Destroy Expired Session
    |--------------------------------------------------------------------------
    */

    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {

        session_destroy();
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect To Login
    |--------------------------------------------------------------------------
    */

    header(
        'Location: /login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Refresh Activity Time
|--------------------------------------------------------------------------
|
| Every valid request to a protected page resets the inactivity timer.
|
*/

$_SESSION['last_activity'] =
    $currentTime;