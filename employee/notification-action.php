<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Employee');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /employee/notifications.php'
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

    exit(
        'Invalid security token.'
    );
}


$action =
    $_POST['action']
    ?? '';


$returnStatus =
    $_POST['return_status']
    ?? 'All';


if (
    !in_array(
        $returnStatus,
        [
            'All',
            'Unread',
            'Read'
        ],
        true
    )
) {

    $returnStatus = 'All';
}


try {

    /*
    |--------------------------------------------------------------------------
    | Mark All
    |--------------------------------------------------------------------------
    */

    if ($action === 'read_all') {

        $stmt =
            $pdo->prepare(
                "UPDATE notifications

                 SET is_read = TRUE

                 WHERE
                    user_id =
                        :user_id

                    AND is_read =
                        FALSE"
            );


        $stmt->execute([
            'user_id' =>
                (int)$_SESSION[
                    'user_id'
                ]
        ]);


        setFlash(
            'success',
            'All notifications marked as read.'
        );


    /*
    |--------------------------------------------------------------------------
    | Individual Notification
    |--------------------------------------------------------------------------
    */

    } elseif (
        in_array(
            $action,
            [
                'read',
                'unread'
            ],
            true
        )
    ) {

        $notificationId =
            filter_input(
                INPUT_POST,
                'notification_id',
                FILTER_VALIDATE_INT
            );


        if (!$notificationId) {

            throw new RuntimeException(
                'Invalid notification.'
            );
        }


        $newValue =
            $action === 'read'
                ? 1
                : 0;


        $stmt =
            $pdo->prepare(
                "UPDATE notifications

                 SET is_read =
                    :is_read

                 WHERE
                    notification_id =
                        :notification_id

                    AND user_id =
                        :user_id"
            );


        $stmt->execute([

            'is_read' =>
                $newValue,

            'notification_id' =>
                $notificationId,

            'user_id' =>
                (int)$_SESSION[
                    'user_id'
                ]
        ]);


        if (
            $stmt->rowCount() === 0
        ) {

            throw new RuntimeException(
                'Notification not found or already has that status.'
            );
        }


        setFlash(
            'success',
            $action === 'read'
                ? 'Notification marked as read.'
                : 'Notification marked as unread.'
        );


    } else {

        throw new RuntimeException(
            'Invalid notification action.'
        );
    }


} catch (Throwable $e) {

    error_log(
        'Employee notification action error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update notification.'
    );
}


header(
    'Location: /employee/notifications.php?status='
    . urlencode(
        $returnStatus
    )
);

exit;