<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/includes/config.php';

$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!defined('API_KEY') || API_KEY === '' || !hash_equals((string) API_KEY, $providedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'codigo' => 'API_KEY_INVALIDA']);
    exit;
}

$petVersionPath = __DIR__ . '/pet/VERSION';
$petVersionContents = is_file($petVersionPath) ? file_get_contents($petVersionPath) : false;
$petVersion = is_string($petVersionContents) ? trim($petVersionContents) : '';

$result = [
    'ok' => true,
    'versao_php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    'configuracao' => true,
    'sessao' => false,
    'banco' => false,
    'versao_pet' => $petVersion !== '' ? $petVersion : null,
    'versao_banco_pet' => null,
];

try {
    require_once __DIR__ . '/includes/session.php';
    start_api_session();
    $result['sessao'] = true;
} catch (Throwable $exception) {
    $result['ok'] = false;
    $result['sessao_erro'] = get_class($exception);
    $result['sessao_codigo'] = (int) $exception->getCode();
}

try {
    require_once __DIR__ . '/includes/database.php';
    $conn = db();
    $conn->query('SELECT 1');
    $result['banco'] = true;
    $schemaResult = $conn->query('SELECT versao FROM pet_schema_migrations ORDER BY aplicado_em DESC, versao DESC LIMIT 1');
    $result['versao_banco_pet'] = $schemaResult->fetch_assoc()['versao'] ?? null;
    if ($result['versao_pet'] !== $result['versao_banco_pet']) {
        $result['ok'] = false;
        $result['banco_erro'] = 'VERSAO_PET_DIVERGENTE';
    }
} catch (Throwable $exception) {
    $result['ok'] = false;
    $result['banco_erro'] = get_class($exception);
    $result['banco_codigo'] = (int) $exception->getCode();
}

http_response_code($result['ok'] ? 200 : 503);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
