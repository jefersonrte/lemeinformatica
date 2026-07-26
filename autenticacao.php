<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/authentication.php';

apply_cors();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_api_key();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

$input = request_json();
$email = strtolower(trim((string) ($input['email'] ?? '')));
$password = (string) ($input['senha'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    json_response([
        'ok' => false,
        'codigo' => 'CREDENCIAIS_INVALIDAS',
        'erro' => 'E-mail ou senha incorretos.'
    ], 401);
}

try {
    $result = authenticate_credentials($email, $password);

    if ($result['status'] === 'blocked') {
        json_response([
            'ok' => false,
            'codigo' => 'LOGIN_BLOQUEADO',
            'erro' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'
        ], 429);
    }

    if ($result['status'] !== 'success') {
        json_response([
            'ok' => false,
            'codigo' => 'CREDENCIAIS_INVALIDAS',
            'erro' => 'E-mail ou senha incorretos.'
        ], 401);
    }

    json_response(['ok' => true, 'usuario' => $result['user']]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'codigo' => 'API_BANCO_INDISPONIVEL',
        'erro' => 'Nao foi possivel validar o acesso agora. Tente novamente em instantes.'
    ], 503);
}
