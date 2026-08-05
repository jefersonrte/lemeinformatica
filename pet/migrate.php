<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

apply_page_security_headers();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

require_api_key();

try {
    $result = pet_apply_migrations();
    json_response([
        'ok' => true,
        'versao_aplicacao' => PET_VERSION,
        'versao_banco' => $result['versao_banco'],
        'migracoes_aplicadas' => $result['aplicadas'],
    ]);
} catch (Throwable $exception) {
    error_log('[PET MIGRATE] ' . get_class($exception) . ': ' . $exception->getMessage());
    json_response([
        'ok' => false,
        'codigo' => 'MIGRACAO_FALHOU',
        'erro' => 'Nao foi possivel atualizar o banco do modulo Pet.',
    ], 500);
}
