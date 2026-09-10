<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';

requireRole('Administrator');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Administrator Dashboard | ELMS</title>
</head>

<body>

<h1>Administrator Dashboard</h1>

<p>
    Logged in as:
    <?= htmlspecialchars(
        $_SESSION['email'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    Role:
    <?= htmlspecialchars(
        $_SESSION['role'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<a href="/logout.php">
    Logout
</a>

</body>

</html>