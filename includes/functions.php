<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectByRole(string $role): never
{
    switch ($role) {

        case 'Administrator':
            header('Location: /admin/dashboard.php');
            break;

        case 'Manager':
            header('Location: /manager/dashboard.php');
            break;

        case 'Employee':
            header('Location: /employee/dashboard.php');
            break;

        default:
            header('Location: /login.php');
            break;
    }

    exit;
}

function generateCsrfToken(): string
{
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($token)
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}

function setFlash(
    string $type,
    string $message
): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];

    unset($_SESSION['flash']);

    return $flash;
}
function logAudit(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null
): void {

    try {

        $userId =
            isset($_SESSION['user_id'])
                ? (int)$_SESSION['user_id']
                : null;


        /*
        |--------------------------------------------------------------------------
        | IP Address
        |--------------------------------------------------------------------------
        */

        $ipAddress =
            $_SERVER[
                'HTTP_X_FORWARDED_FOR'
            ]
            ??
            $_SERVER[
                'REMOTE_ADDR'
            ]
            ??
            null;


        if (
            $ipAddress
            &&
            str_contains(
                $ipAddress,
                ','
            )
        ) {

            $ipParts =
                explode(
                    ',',
                    $ipAddress
                );


            $ipAddress =
                trim(
                    $ipParts[0]
                );
        }


        if ($ipAddress) {

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


        if ($userAgent) {

            $userAgent =
                substr(
                    $userAgent,
                    0,
                    255
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        if ($description) {

            $description =
                substr(
                    $description,
                    0,
                    500
                );
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


    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Audit failure must NOT break main business operation
        |--------------------------------------------------------------------------
        */

        error_log(
            'Audit logging failed: '
            . $e->getMessage()
        );
    }
}