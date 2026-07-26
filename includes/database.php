<?php
if (is_file(__DIR__ . '/database.runtime.php')) {
    require_once __DIR__ . '/database.runtime.php';
}

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = defined('DB_RUNTIME_HOST') ? DB_RUNTIME_HOST : DB_HOST;
    $name = defined('DB_RUNTIME_NAME') ? DB_RUNTIME_NAME : DB_NAME;
    $user = defined('DB_RUNTIME_USER') ? DB_RUNTIME_USER : DB_USER;
    $pass = defined('DB_RUNTIME_PASS') ? DB_RUNTIME_PASS : DB_PASS;

    $conn = new mysqli($host, $user, $pass, $name);
    $conn->set_charset('utf8mb4');

    return $conn;
}
