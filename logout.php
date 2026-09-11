<?php

declare(strict_types=1);

require_once __DIR__
    . '/includes/session.php';

require_once __DIR__
    . '/includes/functions.php';

require_once __DIR__
    . '/config/database.php';


/*
|--------------------------------------------------------------------------
| Audit Logout
|--------------------------------------------------------------------------
|
| Record the logout while the authenticated user's session still exists.
|
*/

if (
    isset(
        $_SESSION['user_id']
    )
) {

    logAudit(
        $pdo,
        'LOGOUT',
        'user',
        (int)$_SESSION[
            'user_id'
        ],
        'User signed out of ELMS.'
    );
}


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
| Destroy Session
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
| Prevent Cached Authenticated Page Reuse
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
| Redirect to Login
|--------------------------------------------------------------------------
*/

header(
    'Location: /login.php'
);

exit;