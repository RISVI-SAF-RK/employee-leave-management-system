<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/role_check.php';

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../config/database.php';


requireRole('Manager');


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header(
        'Location: /manager/notifications.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Validation
|--------------------------------------------------------------------------
*/

$csrfToken =
    $_POST['csrf_token']
    ?? null;


if (
    !is_string(
        $csrfToken
    )
    ||
    !verifyCsrfToken(
        $csrfToken
    )
) {

    http_response_code(403);

    exit(
        'Invalid security token.'
    );
}


/*
|--------------------------------------------------------------------------
| Action
|--------------------------------------------------------------------------
*/

$actionInput =
    $_POST['action']
    ?? '';


$action =
    is_string(
        $actionInput
    )
        ? $actionInput
        : '';


/*
|--------------------------------------------------------------------------
| Return Filter
|--------------------------------------------------------------------------
*/

$returnStatusInput =
    $_POST['return_status']
    ?? 'All';


$returnStatus =
    is_string(
        $returnStatusInput
    )
        ? $returnStatusInput
        : 'All';


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

    $returnStatus =
        'All';
}


try {

    /*
    |--------------------------------------------------------------------------
    | Mark All
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'read_all'
    ) {

        $stmt =
            $pdo->prepare(
                "UPDATE notifications

                 SET is_read =
                    TRUE

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

        $notificationIdInput =
            $_POST['notification_id']
            ?? null;


        $notificationId =
            is_string(
                $notificationIdInput
            )
                ? filter_var(
                    $notificationIdInput,
                    FILTER_VALIDATE_INT
                )
                : false;


        if (
            !$notificationId
            ||
            $notificationId < 1
        ) {

            throw new RuntimeException(
                'Invalid notification.'
            );
        }


        $newValue =
            $action === 'read'
                ? 1
                : 0;


        /*
        |--------------------------------------------------------------------------
        | Update Only The Current User's Notification
        |--------------------------------------------------------------------------
        */

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
        'Manager notification action error: '
        . $e->getMessage()
    );


    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update notification.'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header(
    'Location: /manager/notifications.php?status='
    . urlencode(
        $returnStatus
    )
);

exit;
