<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$email = getenv('INITIAL_ADMIN_EMAIL');
$password = getenv('INITIAL_ADMIN_PASSWORD');

if (!$email || !$password) {
    exit("Admin environment variables are missing.\n");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Invalid administrator email.\n");
}

if (strlen($password) < 8) {
    exit("Administrator password must contain at least 8 characters.\n");
}

try {

    $roleStmt = $pdo->prepare(
        "SELECT role_id
         FROM roles
         WHERE role_name = 'Administrator'
         LIMIT 1"
    );

    $roleStmt->execute();

    $role = $roleStmt->fetch();

    if (!$role) {
        throw new RuntimeException(
            'Administrator role was not found.'
        );
    }

    $checkStmt = $pdo->prepare(
        "SELECT user_id
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $checkStmt->execute([$email]);

    if ($checkStmt->fetch()) {
        exit("Administrator account already exists.\n");
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $insertStmt = $pdo->prepare(
        "INSERT INTO users
            (email, password_hash, role_id, status)
         VALUES
            (?, ?, ?, 'Active')"
    );

    $insertStmt->execute([
        $email,
        $passwordHash,
        $role['role_id']
    ]);

    echo "Administrator account created successfully.\n";

} catch (Throwable $e) {

    error_log(
        'Create admin error: ' .
        $e->getMessage()
    );

    exit("Administrator account creation failed.\n");
}