<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
http_response_code(410);
echo json_encode([
    'ok' => false,
    'erro' => 'Endpoint desativado. O modulo GOV agora oferece somente licitacoes.',
    'licitacoes' => '/gov/api/licitacoes.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
