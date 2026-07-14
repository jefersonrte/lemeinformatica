<?php
function apply_page_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function start_api_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();

    if (isset($_SESSION['last_activity'])
        && ($now - (int) $_SESSION['last_activity']) > SESSION_IDLE_LIMIT_SECONDS) {
        clear_api_session();
        session_start();
    }

    $_SESSION['last_activity'] = $now;

    if (empty($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = $now;
    }

    if (($now - (int) $_SESSION['last_regeneration']) > SESSION_REGENERATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = $now;
    }

    if (empty($_SESSION[CSRF_SESSION_KEY])) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
}

function api_csrf_token(): string
{
    start_api_session();
    return (string) $_SESSION[CSRF_SESSION_KEY];
}

function validate_api_csrf(?string $token): bool
{
    start_api_session();
    return is_string($token)
        && $token !== ''
        && hash_equals((string) ($_SESSION[CSRF_SESSION_KEY] ?? ''), $token);
}

function current_api_user(): ?array
{
    start_api_session();

    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['usuario_id'],
        'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
        'perfil' => (string) ($_SESSION['usuario_perfil'] ?? ''),
    ];
}

function login_api_user(array $user): void
{
    start_api_session();
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $user['id'];
    $_SESSION['usuario_nome'] = (string) $user['nome'];
    $_SESSION['usuario_email'] = (string) $user['email'];
    $_SESSION['usuario_perfil'] = (string) $user['perfil'];
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
}

function clear_api_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function require_api_page_login(array $allowedRoles = []): array
{
    $user = current_api_user();

    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    if ($allowedRoles && !in_array($user['perfil'], $allowedRoles, true)) {
        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }

    return $user;
}
