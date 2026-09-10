<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';

requireRole('Employee');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard | ELMS</title>
</head>

<body>

<h1>Employee Dashboard</h1>

<p>Welcome to the Employee area.</p>

<a href="/logout.php">
    Logout
</a>

</body>

</html>