<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Escape HTML Output
|--------------------------------------------------------------------------
*/

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect User By Role
|--------------------------------------------------------------------------
*/

function redirectByRole(
    string $role
): never {

    switch ($role) {

        case 'Administrator':

            header(
                'Location: /admin/dashboard.php'
            );

            break;


        case 'Manager':

            header(
                'Location: /manager/dashboard.php'
            );

            break;


        case 'Employee':

            header(
                'Location: /employee/dashboard.php'
            );

            break;


        default:

            header(
                'Location: /login.php'
            );

            break;
    }


    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Token Generation
|--------------------------------------------------------------------------
*/

function generateCsrfToken(): string
{
    if (
        empty(
            $_SESSION['csrf_token']
        )
        ||
        !is_string(
            $_SESSION['csrf_token']
        )
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }


    return $_SESSION[
        'csrf_token'
    ];
}


/*
|--------------------------------------------------------------------------
| CSRF Token Verification
|--------------------------------------------------------------------------
*/

function verifyCsrfToken(
    ?string $token
): bool {

    if (
        !isset(
            $_SESSION['csrf_token']
        )
        ||
        !is_string(
            $_SESSION['csrf_token']
        )
        ||
        !is_string(
            $token
        )
    ) {

        return false;
    }


    return hash_equals(
        $_SESSION[
            'csrf_token'
        ],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

function setFlash(
    string $type,
    string $message
): void {

    $_SESSION['flash'] = [

        'type' =>
            $type,

        'message' =>
            $message
    ];
}


/*
|--------------------------------------------------------------------------
| Get Flash Message
|--------------------------------------------------------------------------
*/

function getFlash(): ?array
{
    if (
        !isset(
            $_SESSION['flash']
        )
        ||
        !is_array(
            $_SESSION['flash']
        )
    ) {

        return null;
    }


    $flash =
        $_SESSION[
            'flash'
        ];


    unset(
        $_SESSION['flash']
    );


    return $flash;
}


/*
|--------------------------------------------------------------------------
| Audit Logging
|--------------------------------------------------------------------------
*/

function logAudit(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null
): void {

    try {

        /*
        |--------------------------------------------------------------------------
        | Current User
        |--------------------------------------------------------------------------
        */

        $userId =
            isset(
                $_SESSION['user_id']
            )
                ? (int)$_SESSION[
                    'user_id'
                ]
                : null;


        /*
        |--------------------------------------------------------------------------
        | Limit Audit Field Lengths
        |--------------------------------------------------------------------------
        |
        | Matches the audit_logs database column sizes.
        |
        */

        $action =
            substr(
                $action,
                0,
                100
            );


        if (
            $entityType !== null
        ) {

            $entityType =
                substr(
                    $entityType,
                    0,
                    60
                );
        }


        if (
            $description !== null
        ) {

            $description =
                substr(
                    $description,
                    0,
                    500
                );
        }


        /*
        |--------------------------------------------------------------------------
        | IP Address
        |--------------------------------------------------------------------------
        |
        | Do not directly trust HTTP_X_FORWARDED_FOR because it can be
        | supplied or spoofed by a client.
        |
        | REMOTE_ADDR represents the network peer connected to PHP.
        | Behind Railway this may sometimes be a proxy address, but that is
        | safer than trusting an arbitrary forwarded header.
        |
        */

        $ipAddress =
            $_SERVER[
                'REMOTE_ADDR'
            ]
            ?? null;


        if (
            is_string(
                $ipAddress
            )
        ) {

            $ipAddress =
                trim(
                    $ipAddress
                );


            if (
                !filter_var(
                    $ipAddress,
                    FILTER_VALIDATE_IP
                )
            ) {

                $ipAddress =
                    null;
            }
        } else {

            $ipAddress =
                null;
        }


        if (
            $ipAddress !== null
        ) {

            $ipAddress =
                substr(
                    $ipAddress,
                    0,
                    45
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Browser / Device
        |--------------------------------------------------------------------------
        */

        $userAgent =
            $_SERVER[
                'HTTP_USER_AGENT'
            ]
            ?? null;


        if (
            is_string(
                $userAgent
            )
        ) {

            $userAgent =
                substr(
                    $userAgent,
                    0,
                    255
                );

        } else {

            $userAgent =
                null;
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Audit Record
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare(
                "INSERT INTO audit_logs
                (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    description,
                    ip_address,
                    user_agent
                )

                VALUES
                (
                    :user_id,
                    :action,
                    :entity_type,
                    :entity_id,
                    :description,
                    :ip_address,
                    :user_agent
                )"
            );


        $stmt->execute([

            'user_id' =>
                $userId,

            'action' =>
                $action,

            'entity_type' =>
                $entityType,

            'entity_id' =>
                $entityId,

            'description' =>
                $description,

            'ip_address' =>
                $ipAddress,

            'user_agent' =>
                $userAgent
        ]);


    } catch (
        Throwable $e
    ) {

        /*
        |--------------------------------------------------------------------------
        | Audit Failure Must Not Break Main Business Operation
        |--------------------------------------------------------------------------
        */

        error_log(
            'Audit logging failed: '
            . $e->getMessage()
        );
    }
}