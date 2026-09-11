<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Production Error Handling
|--------------------------------------------------------------------------
|
| Report all PHP errors internally, but never expose technical details
| to website users. Railway can capture logged errors.
|
*/

error_reporting(E_ALL);

ini_set(
    'display_errors',
    '0'
);

ini_set(
    'display_startup_errors',
    '0'
);

ini_set(
    'log_errors',
    '1'
);


/*
|--------------------------------------------------------------------------
| PHP Session Security
|--------------------------------------------------------------------------
*/

ini_set(
    'session.use_strict_mode',
    '1'
);

ini_set(
    'session.use_only_cookies',
    '1'
);


/*
|--------------------------------------------------------------------------
| HTTP Security Headers
|--------------------------------------------------------------------------
*/

if (!headers_sent()) {

    /*
    | Prevent browsers from guessing
    | a different MIME/content type.
    */
    header(
        'X-Content-Type-Options: nosniff'
    );


    /*
    | Prevent ELMS pages from being
    | embedded inside frames/iframes.
    | Helps protect against clickjacking.
    */
    header(
        'X-Frame-Options: DENY'
    );


    /*
    | Limit referrer information sent
    | to other websites.
    */
    header(
        'Referrer-Policy: strict-origin-when-cross-origin'
    );


    /*
    | ELMS currently does not require
    | browser camera, microphone or
    | geolocation access.
    */
    header(
        'Permissions-Policy: geolocation=(), camera=(), microphone=()'
    );
}


/*
|--------------------------------------------------------------------------
| Start Secure Session
|--------------------------------------------------------------------------
*/

if (
    session_status()
    === PHP_SESSION_NONE
) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);


    session_start();
}