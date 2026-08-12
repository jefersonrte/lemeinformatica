<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$databaseOk = false;
try {
    $databaseOk = getDB()->query('SELECT 1')->fetchColumn() === 1;
} catch (Throwable) {
    $databaseOk = false;
}

http_response_code($databaseOk ? 200 : 503);
echo json_encode([
    'ok' => $databaseOk,
    'app' => 'orca',
    'version' => APP_VERSION,
    'database' => $databaseOk ? 'ok' : 'unavailable',
    'timestamp' => gmdate(DATE_ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
