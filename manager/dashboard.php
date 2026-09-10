<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';

requireRole('Manager');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard | ELMS</title>
</head>

<body>

<h1>Manager Dashboard</h1>

<p>Welcome to the Manager area.</p>

<a href="/logout.php">
    Logout
</a>

</body>

</html>