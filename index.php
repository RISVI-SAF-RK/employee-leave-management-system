<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {

    redirectByRole(
        $_SESSION['role']
    );
}

header('Location: /login.php');

exit;