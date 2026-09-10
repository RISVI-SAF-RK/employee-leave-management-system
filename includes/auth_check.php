<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: /login.php');

    exit;
}