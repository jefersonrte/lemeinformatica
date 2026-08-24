<?php
declare(strict_types=1);

use App\Infrastructure\DatabaseConnection;
use App\Support\Config;

function databaseConnection(): DatabaseConnection
{
    static $database = null;
    if (!$database instanceof DatabaseConnection) {
        $database = new DatabaseConnection((array) Config::get('database', []));
    }
    return $database;
}

function getDB(): PDO
{
    return databaseConnection()->get();
}
