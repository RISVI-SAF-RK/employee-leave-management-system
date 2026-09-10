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