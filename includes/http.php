<?php
function apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin && in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-KEY, X-CSRF-TOKEN, Authorization');
    header('Access-Control-Max-Age: 86400');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_key_from_request(): string
{
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim($_SERVER['HTTP_X_API_KEY']);
    }

    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($authorization, 'Bearer ') === 0) {
        return trim(substr($authorization, 7));
    }

    return '';
}

function require_api_key(): void
{
    if (API_KEY === '' || API_KEY === 'TROQUE_ESTA_CHAVE_API_FORTE') {
        json_response([
            'ok' => false,
            'erro' => 'API_KEY ainda nao foi configurada no servidor principal.'
        ], 500);
    }

    if (!has_valid_api_key()) {
        json_response([
            'ok' => false,
            'erro' => 'Nao autorizado. Informe a chave X-API-KEY correta.'
        ], 401);
    }
}

function has_valid_api_key(): bool
{
    $receivedKey = api_key_from_request();
    return API_KEY !== ''
        && API_KEY !== 'TROQUE_ESTA_CHAVE_API_FORTE'
        && $receivedKey !== ''
        && hash_equals(API_KEY, $receivedKey);
}

function require_api_or_session(): string
{
    if (has_valid_api_key()) {
        return 'api_key';
    }

    if (function_exists('current_api_user') && current_api_user() !== null) {
        return 'session';
    }

    json_response(['ok' => false, 'erro' => 'Usuario nao autenticado.'], 401);
}

function require_session_csrf_for_state_change(string $authMode): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($authMode !== 'session' || !in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!function_exists('validate_api_csrf') || !validate_api_csrf($token)) {
        json_response([
            'ok' => false,
            'erro' => 'Sessao de seguranca expirada. Atualize a pagina e tente novamente.'
        ], 419);
    }
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        json_response([
            'ok' => false,
            'erro' => 'JSON invalido.',
            'detalhe' => json_last_error_msg()
        ], 400);
    }

    return $data;
}

function clean_text(mixed $value): string
{
    return trim((string) $value);
}

function positive_int(mixed $value, int $default, int $max): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 1) {
        return $default;
    }
    return min($number, $max);
}

function method_override(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST') {
        $data = request_json();
        if (isset($data['_method'])) {
            $override = strtoupper((string) $data['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                return $override;
            }
        }
    }
    return $method;
}
