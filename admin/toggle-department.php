<?php

declare(strict_types=1);

require_once __DIR__ .
    '/../includes/role_check.php';

requireRole('Administrator');

require_once __DIR__ .
    '/../includes/functions.php';

require_once __DIR__ .
    '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /admin/departments.php'
    );

    exit;
}

if (
    !verifyCsrfToken(
        $_POST['csrf_token'] ?? null
    )
) {

    http_response_code(403);

    exit('Invalid security token.');
}

$departmentId = filter_input(
    INPUT_POST,
    'department_id',
    FILTER_VALIDATE_INT
);

if (!$departmentId) {

    setFlash(
        'danger',
        'Invalid department.'
    );

    header(
        'Location: /admin/departments.php'
    );

    exit;
}

$stmt = $pdo->prepare(
    "SELECT status
     FROM departments
     WHERE department_id = :department_id
     LIMIT 1"
);

$stmt->execute([
    'department_id' => $departmentId
]);

$department = $stmt->fetch();

if (!$department) {

    setFlash(
        'danger',
        'Department not found.'
    );

    header(
        'Location: /admin/departments.php'
    );

    exit;
}

$newStatus =
    $department['status'] === 'Active'
        ? 'Inactive'
        : 'Active';

$update = $pdo->prepare(
    "UPDATE departments
     SET status = :status
     WHERE department_id = :department_id"
);

$update->execute([
    'status' => $newStatus,
    'department_id' => $departmentId
]);

setFlash(
    'success',
    'Department status updated successfully.'
);

header(
    'Location: /admin/departments.php'
);

exit;