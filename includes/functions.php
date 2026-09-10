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