<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole('Administrator');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /admin/leave-types.php'
    );

    exit;
}


if (
    !verifyCsrfToken(
        $_POST['csrf_token']
        ?? null
    )
) {

    http_response_code(403);

    exit('Invalid security token.');
}


$leaveTypeId = filter_input(
    INPUT_POST,
    'leave_type_id',
    FILTER_VALIDATE_INT
);


if (!$leaveTypeId) {

    setFlash(
        'danger',
        'Invalid leave type selected.'
    );

    header(
        'Location: /admin/leave-types.php'
    );

    exit;
}


try {

    $stmt =
        $pdo->prepare(
            "SELECT
                leave_type_id,
                status
             FROM leave_types
             WHERE leave_type_id =
                :leave_type_id
             LIMIT 1"
        );


    $stmt->execute([
        'leave_type_id' =>
            $leaveTypeId
    ]);


    $leaveType =
        $stmt->fetch();


    if (!$leaveType) {

        throw new RuntimeException(
            'Leave type not found.'
        );
    }


    $newStatus =
        $leaveType['status']
        === 'Active'
            ? 'Inactive'
            : 'Active';


    $updateStmt =
        $pdo->prepare(
            "UPDATE leave_types
             SET status = :status
             WHERE leave_type_id =
                :leave_type_id"
        );


    $updateStmt->execute([
        'status' =>
            $newStatus,

        'leave_type_id' =>
            $leaveTypeId
    ]);


    setFlash(
        'success',
        'Leave type '
        . strtolower($newStatus)
        . ' successfully.'
    );


} catch (Throwable $e) {

    error_log(
        'Toggle leave type error: ' .
        $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update leave type status.'
    );
}


header(
    'Location: /admin/leave-types.php'
);

exit;