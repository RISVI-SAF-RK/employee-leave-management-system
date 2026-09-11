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
| Record Logout Before Destroying Session
|--------------------------------------------------------------------------
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
    ini_get(
        'session.use_cookies'
    )
) {

    $params =
        session_get_cookie_params();


    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


/*
|--------------------------------------------------------------------------
| Destroy Session
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| Return To Login
|--------------------------------------------------------------------------
*/

header(
    'Location: /login.php'
);

exit;