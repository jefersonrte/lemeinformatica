<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $projectRoot . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once $projectRoot . '/config/config.php';

date_default_timezone_set((string) \App\Support\Config::get('app.timezone', 'America/Sao_Paulo'));
$isProduction = APP_ENV === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $exception) use ($isProduction): void {
    error_log('[orca] ' . $exception);
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $detail = $isProduction ? '' : '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Indisponível</title>'
        . '<body><h1>Serviço temporariamente indisponível</h1><p>Tente novamente em instantes.</p>' . $detail . '</body></html>';
});

require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/includes/auth.php';
require_once $projectRoot . '/helpers/layout.php';
