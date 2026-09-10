<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

function requireRole(string $requiredRole): void
{
    if (
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== $requiredRole
    ) {

        http_response_code(403);

        echo '<h1>403 - Access Denied</h1>';
        echo '<p>You do not have permission to access this page.</p>';

        exit;
    }
}